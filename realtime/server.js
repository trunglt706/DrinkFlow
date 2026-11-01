import crypto from 'node:crypto';
import fs from 'node:fs';
import { createServer } from 'node:http';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { Server } from 'socket.io';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  try {
    const content = fs.readFileSync(filePath, 'utf8');
    for (const line of content.split(/\r?\n/)) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('#')) continue;
      const match = trimmed.match(/^([A-Za-z0-9_]+)\s*=\s*(.*)$/);
      if (match) {
        const key = match[1];
        let val = match[2].trim();
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
          val = val.slice(1, -1);
        }
        if (!process.env[key]) {
          process.env[key] = val;
        }
      }
    }
  } catch (err) {
    console.warn(`Could not load ${filePath}:`, err.message);
  }
}

// Automatically load from realtime/.env or ../src/.env
loadEnvFile(path.join(__dirname, '.env'));
loadEnvFile(path.join(__dirname, '../src/.env'));

const port = Number(process.env.PORT || 3001);
const secret = process.env.SOCKET_TOKEN_SECRET || process.env.APP_KEY || '';
const internalSecret = process.env.REALTIME_INTERNAL_SECRET || '';
if (!secret) throw new Error('SOCKET_TOKEN_SECRET or APP_KEY is required (check .env in realtime/ or src/)');
const maxBodyBytes = 256 * 1024;
// Events addressed to a single user, trusted device or admin account (see PublishRealtimeEvent): delivered on user_channel only.
const privateEvents = new Set(['notification.created', 'admin.notification.created', 'superadmin.notification.created', 'room.membership.updated', 'session.force_reload', 'sponsor_draw.event', 'game_room.status_changed']);
// Events for every connected page, including anonymous public visitors: delivered on the `public` channel only.
const broadcastEvents = new Set(['system.maintenance']);
// Events that are not tied to a room, so they are sent with room_id 0.
const roomlessEvents = new Set(['notification.created', 'admin.notification.created', 'superadmin.notification.created', 'session.force_reload', 'system.maintenance', 'game_room.status_changed']);
// draw:{id} = viewers of one sponsor draw session (token claim draw_session_id, issued only while its link is valid).
// game:{id} = visitors of one game room's public page (token claim game_room_id, issued only while its link is open).
const userChannelPattern = /^(?:(?:user|global_user|admin|superadmin|draw|game):\d+|device:[A-Za-z0-9-]{1,128})$/;

const decode = value => Buffer.from(value.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - value.length % 4) % 4), 'base64').toString('utf8');
const verify = token => {
  if (typeof token !== 'string') return null;
  const [encoded, signature] = token.split('.', 2);
  if (!encoded || !signature) return null;
  const expected = crypto.createHmac('sha256', secret).update(encoded).digest('hex');
  if (signature.length !== expected.length || !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) return null;
  try {
    const payload = JSON.parse(decode(encoded));
    return ['user', 'admin', 'superadmin', 'draw_viewer', 'game_viewer'].includes(payload.actor_type) && Number(payload.exp) >= Math.floor(Date.now() / 1000) ? payload : null;
  } catch { return null; }
};

let authenticationFailures = 0;
const recentDisconnects = [];
const httpServer = createServer((req, res) => {
  // Match on the path only, so a trailing slash or query string (e.g. from a proxy or uptime checker) still hits the route.
  const pathname = new URL(req.url || '/', 'http://localhost').pathname.replace(/\/+$/, '') || '/';
  if (req.method === 'POST' && pathname === '/internal/emit') {
    if (!secretsMatch(req.headers['x-realtime-secret'])) {
      res.writeHead(401, { 'Content-Type': 'application/json' });
      return res.end(JSON.stringify({ error: 'unauthorized' }));
    }
    let body = '';
    let bodyBytes = 0;
    let bodyRejected = false;
    req.on('data', chunk => {
      if (bodyRejected) return;
      bodyBytes += chunk.length;
      if (bodyBytes > maxBodyBytes) {
        bodyRejected = true;
        res.writeHead(413, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'payload_too_large' }));
        req.resume();
        return;
      }
      body += chunk;
    });
    req.on('end', () => {
      if (bodyRejected) return;
      try {
        const input = JSON.parse(body || '{}');
        const allowedEvents = new Set([
          'order.created', 'order.updated', 'order.deleted',
          'order.payment_submitted', 'order.payment_approved', 'debt.payment_approved',
          'debt.payment_submitted',
          'campaign.created', 'campaign.updated', 'campaign.deleted', 'campaign.closed',
          'campaign.cancelled', 'campaign.delivering',
          'campaign.menu.updated', 'campaign.menu.deleted', 'campaign.participant.declined', 'campaign.participant.rejoined', 'notification.created',
          'admin.notification.created', 'superadmin.notification.created',
          'room.membership.updated', 'session.force_reload', 'system.maintenance', 'sponsor_draw.event',
          'game_room.status_changed'
        ]);
        const roomId = Number(input.room_id);
        const requiresRoom = !roomlessEvents.has(input.event);
        if (!allowedEvents.has(input.event) || typeof input.room_id !== 'number' || !Number.isSafeInteger(roomId) || (requiresRoom && roomId < 1) || (!requiresRoom && roomId < 0)) {
          res.writeHead(422, { 'Content-Type': 'application/json' });
          return res.end(JSON.stringify({ error: 'invalid_event' }));
        }
        const payload = input.payload && typeof input.payload === 'object' && !Array.isArray(input.payload) ? input.payload : {};
        const userChannel = typeof input.user_channel === 'string' && userChannelPattern.test(input.user_channel) ? input.user_channel : null;
        if (broadcastEvents.has(input.event)) {
          io.to('public').emit(input.event, payload);
          res.writeHead(202, { 'Content-Type': 'application/json' });
          return res.end(JSON.stringify({ delivered: true }));
        }
        // Private events belong to one user only: never fan them out to the whole room, otherwise
        // every member receives other members' notifications / membership changes.
        if (privateEvents.has(input.event)) {
          if (!userChannel) {
            res.writeHead(422, { 'Content-Type': 'application/json' });
            return res.end(JSON.stringify({ error: 'user_channel_required' }));
          }
          io.to(userChannel).emit(input.event, payload);
        } else {
          // One emit to the union of channels, so a socket in both rooms gets the event once.
          io.to(userChannel ? [`room:${roomId}`, userChannel] : `room:${roomId}`).emit(input.event, payload);
        }
        res.writeHead(202, { 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({ delivered: true }));
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        return res.end(JSON.stringify({ error: 'invalid_json' }));
      }
    });
    return;
  }
  if (pathname === '/health' && !secretsMatch(req.headers['x-realtime-secret'])) {
    res.writeHead(401, { 'Content-Type': 'application/json' });
    return res.end(JSON.stringify({ error: 'unauthorized' }));
  }
  if (pathname !== '/health') {
    res.writeHead(404);
    return res.end();
  }
  const connectionsByRoom = {};
  let connectedUsers = 0;
  let connectedAdmins = 0;
  let connectedSuperadmins = 0;
  let connectedGuests = 0;
  for (const socket of io?.of('/').sockets.values() || []) {
    const claims = socket.data.claims || {};
    if (claims.actor_type === 'user') connectedUsers += 1;
    if (claims.actor_type === 'admin') connectedAdmins += 1;
    if (claims.actor_type === 'superadmin') connectedSuperadmins += 1;
    if (claims.actor_type === 'guest') connectedGuests += 1;
    for (const roomId of claims.room_ids || (claims.room_id ? [claims.room_id] : [])) connectionsByRoom[roomId] = (connectionsByRoom[roomId] || 0) + 1;
  }
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ connected_users: connectedUsers, connected_admins: connectedAdmins, connected_superadmins: connectedSuperadmins, connected_guests: connectedGuests, connected_guests: connectedGuests, connections_by_room: connectionsByRoom, recent_disconnects: recentDisconnects, authentication_failures: authenticationFailures }));
});
const io = new Server(httpServer, { cors: { origin: process.env.CORS_ORIGIN || '*', credentials: true } });

function secretsMatch(candidate) {
  if (typeof candidate !== 'string' || !internalSecret) return false;
  const expected = Buffer.from(internalSecret);
  const actual = Buffer.from(candidate);
  return expected.length === actual.length && crypto.timingSafeEqual(expected, actual);
}

io.use((socket, next) => {
  const rawToken = socket.handshake.auth?.token || socket.handshake.headers.authorization?.replace(/^Bearer\s+/i, '');
  // No token at all: an anonymous visitor (public pages, admin pages without a room) that may only
  // join the `public` channel, which carries nothing but system-wide notices such as maintenance.
  if (!rawToken) { socket.data.claims = { actor_type: 'guest' }; return next(); }
  const claims = verify(rawToken);
  if (!claims) { authenticationFailures += 1; return next(new Error('Invalid or expired socket token')); }
  socket.data.claims = claims;
  next();
});

io.on('connection', socket => {
  const claims = socket.data.claims;
  const allowed = new Set();
  // Everyone except superadmins (who bypass maintenance) hears system-wide notices.
  if (claims.actor_type !== 'superadmin') allowed.add('public');
  if (claims.actor_type === 'user' && Number.isInteger(Number(claims.room_user_id))) allowed.add(`user:${claims.room_user_id}`);
  if (claims.actor_type === 'user' && Number.isInteger(Number(claims.global_user_id))) allowed.add(`global_user:${claims.global_user_id}`);
  if (claims.actor_type === 'user' && typeof claims.device_uuid === 'string' && /^[A-Za-z0-9-]{1,128}$/.test(claims.device_uuid)) allowed.add(`device:${claims.device_uuid}`);
  if (claims.actor_type === 'admin') allowed.add(`admin:${claims.admin_id}`);
  // Sponsor draw viewer: only that session's channel (no room / user channels), until the token expires.
  if (claims.actor_type === 'draw_viewer' && Number.isInteger(Number(claims.draw_session_id)) && Number(claims.draw_session_id) > 0) allowed.add(`draw:${claims.draw_session_id}`);
  // Public game room visitor: only that room's channel (start / finish make the page reload).
  if (claims.actor_type === 'game_viewer' && Number.isInteger(Number(claims.game_room_id)) && Number(claims.game_room_id) > 0) allowed.add(`game:${claims.game_room_id}`);
  if (claims.actor_type === 'superadmin') {
    allowed.add('superadmin');
    allowed.add('system');
    // Private inbox of this account only (platform alerts such as a new Agent registration).
    if (Number.isInteger(Number(claims.superadmin_id)) && Number(claims.superadmin_id) > 0) allowed.add(`superadmin:${claims.superadmin_id}`);
  }
  for (const roomId of claims.room_ids || (claims.room_id ? [claims.room_id] : [])) allowed.add(`room:${roomId}`);
  for (const channel of allowed) socket.join(channel);
  socket.on('disconnect', reason => { recentDisconnects.unshift({ actor_type: claims.actor_type, reason, at: new Date().toISOString() }); recentDisconnects.splice(20); });
  socket.on('subscribe', channel => {
    if (allowed.has(channel)) socket.join(channel);
    else socket.emit('error', { message: 'Channel is not authorized' });
  });
});

// HOST defaults to all interfaces so Docker keeps working; native VPS deployments set HOST=127.0.0.1 behind Nginx.
const host = process.env.HOST || '0.0.0.0';
httpServer.listen(port, host, () => console.log(`DrinkFlow realtime listening on ${host}:${port}`));
