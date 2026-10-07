# Yêu cầu triển khai: Landing page quay sponsor theo chiến dịch

## 1. Mục tiêu

Xây dựng chức năng để admin tạo một phiên quay sponsor tạm thời cho một chiến dịch. Người dùng thuộc chiến dịch có thể đăng nhập bằng link phiên để xem thông tin và kết quả. Chỉ admin được thực hiện thao tác quay và xác nhận gửi kết quả.

Mỗi lượt quay chọn một user đủ điều kiện. Một user chỉ được trúng tối đa một lần trong phiên/chiến dịch quay. Khi đủ số sponsor cần chọn, admin có thể xác nhận danh sách và gửi thông báo qua Notify Gateway (ví dụ Chatwork, Telegram).

## 2. Quy tắc nghiệp vụ đã chốt

1. User đủ điều kiện khi đồng thời:
   - Thuộc chiến dịch đang được quay.
   - Đang ở trạng thái active tại thời điểm chốt danh sách đủ điều kiện.
2. Danh sách user đủ điều kiện được xác định ở backend. Frontend chỉ hiển thị danh sách và kết quả; không tự quyết định user trúng.
3. Mỗi user chỉ được trúng một lần trong cùng phiên quay. User đã trúng phải bị loại khỏi các lượt quay tiếp theo.
4. Trên landing page chỉ hiển thị tên người dùng. Không hiển thị email, số điện thoại, mã định danh, thông tin tài khoản hay dữ liệu cá nhân khác.
5. Không bắt buộc chọn chiến dịch tiếp theo nhận sponsor. Nếu hệ thống có chiến dịch đích phù hợp, admin có thể chọn chiến dịch đó; trường này là tùy chọn.
6. User không có quyền từ chối kết quả. Không xây dựng luồng từ chối, xác nhận nhận giải hoặc quay lại do user từ chối.
7. Chỉ admin được phép bắt đầu phiên, thực hiện lượt quay, hủy phiên (nếu được phép), xác nhận kết quả và gửi thông báo.
8. User đăng nhập và thuộc chiến dịch mới được truy cập landing page. User không thuộc chiến dịch bị từ chối truy cập.
9. Link phiên có thời hạn mặc định khoảng 1 giờ. Khi link hết hạn, mọi người dùng đều không thể truy cập link đó, kể cả admin. Không tự động gia hạn link.
10. Link hết hạn không xóa phiên và kết quả đã lưu. Admin có thể tạo link/phiên truy cập mới theo chính sách hệ thống; kết quả cũ vẫn tra cứu trong trang quản trị.
11. Khi số sponsor được chọn đạt số lượng đã cấu hình, hệ thống không cho quay thêm trong phiên đó.
12. Kết quả chỉ được gửi thông báo sau khi admin chủ động xác nhận. Không tự gửi chỉ vì đã quay đủ số lượng.

## 3. Đối tượng và quyền

| Đối tượng | Quyền |
|---|---|
| Admin | Tạo cấu hình phiên; phát hành link; xem danh sách đủ điều kiện; quay; xác nhận kết quả; gửi và xem trạng thái thông báo; xem lịch sử phiên. |
| User thuộc chiến dịch | Đăng nhập, truy cập link còn hạn, xem thông tin chiến dịch, danh sách tên user đủ điều kiện và kết quả theo chính sách hiển thị. Không được quay hoặc thay đổi dữ liệu. |
| User không thuộc chiến dịch | Không được truy cập nội dung phiên hoặc danh sách user. |
| Người chưa đăng nhập | Được chuyển đến trang đăng nhập hoặc nhận lỗi xác thực; sau đăng nhập phải kiểm tra lại quyền thành viên chiến dịch và hạn link. |

## 4. Luồng nghiệp vụ

### 4.1. Admin tạo phiên quay

1. Admin mở chức năng quay sponsor trong trang quản trị.
2. Chọn chiến dịch nguồn.
3. Hệ thống tải danh sách user thuộc chiến dịch và đang active.
4. Admin nhập số lượng sponsor cần chọn.
5. Admin có thể chọn chiến dịch tiếp theo nhận sponsor nếu cần; không chọn vẫn được phép tiếp tục.
6. Hệ thống kiểm tra dữ liệu hợp lệ, chốt snapshot danh sách user đủ điều kiện và tạo phiên ở trạng thái `ready`.
7. Admin phát hành link truy cập. Link có thời hạn mặc định 1 giờ, có thời điểm hết hạn rõ ràng.

### 4.2. User truy cập landing page

1. User mở link và đăng nhập nếu chưa đăng nhập.
2. Backend kiểm tra lần lượt:
   - Link/phiên tồn tại và chưa hết hạn.
   - Phiên được phép truy cập.
   - User thuộc chiến dịch nguồn.
3. Nếu hợp lệ, user xem landing page với tên chiến dịch, số lượng cần chọn, tiến độ và các kết quả được phép hiển thị.
4. Nếu link hết hạn, hiển thị trang thông báo link đã hết hạn; không trả dữ liệu chiến dịch, danh sách user hay kết quả từ endpoint phiên.
5. Nếu user không thuộc chiến dịch, trả lỗi truy cập phù hợp và không tiết lộ thông tin phiên.

### 4.3. Admin thực hiện quay

1. Admin bấm `Quay lượt tiếp theo`.
2. Backend kiểm tra quyền admin, link/phiên còn hạn, trạng thái phiên, số lượng mục tiêu và số người còn đủ điều kiện.
3. Backend chọn ngẫu nhiên một user từ danh sách snapshot chưa trúng.
4. Backend ghi kết quả lượt quay trong transaction để tránh hai request đồng thời chọn cùng một user hoặc vượt quá số lượng.
5. Frontend hiển thị hiệu ứng quay và tên user trúng do backend trả về.
6. Cập nhật tiến độ `đã chọn / cần chọn`.
7. Khi đủ số lượng, khóa thao tác quay và chuyển phiên sang trạng thái `completed` hoặc trạng thái tương đương chờ xác nhận.

Nếu số lượng user đủ điều kiện còn lại ít hơn số sponsor cần chọn, không được chọn trùng. Hiển thị lỗi cho admin và cho phép admin điều chỉnh số lượng mục tiêu hoặc hủy phiên theo quyền/chính sách; mọi thay đổi phải được ghi log.

### 4.4. Admin xác nhận và gửi kết quả

1. Khi đã đủ số lượng, admin xem lại danh sách tên người trúng.
2. Admin bấm `Xác nhận và gửi thông báo`.
3. Backend lưu thời điểm xác nhận, người xác nhận và snapshot kết quả cuối cùng.
4. Tạo yêu cầu gửi qua Notify Gateway cho các kênh được cấu hình.
5. Ghi trạng thái gửi riêng theo từng kênh, gồm thời gian, kết quả thành công/thất bại và mã tham chiếu nếu gateway trả về.
6. Cho phép gửi lại kênh thất bại mà không gửi trùng kênh đã thành công.
7. Các yêu cầu xác nhận/gửi lặp lại phải có tính idempotent, không tạo thông báo trùng ngoài ý muốn.

## 5. Trạng thái phiên đề xuất

- `draft`: cấu hình chưa phát hành.
- `ready`: đã chốt danh sách đủ điều kiện, sẵn sàng truy cập/quay.
- `drawing`: đã bắt đầu quay.
- `completed`: đã chọn đủ số lượng, chờ admin xác nhận gửi.
- `confirmed`: admin đã xác nhận kết quả.
- `notification_pending`: thông báo đang chờ xử lý.
- `notified`: thông báo đã gửi thành công đến các kênh được cấu hình.
- `notification_failed`: có ít nhất một kênh gửi thất bại.
- `cancelled`: phiên đã bị admin hủy theo chính sách.
- `expired`: link đã hết hạn. Việc hết hạn link không được xóa dữ liệu phiên hoặc kết quả.

Trạng thái phiên và trạng thái link nên được quản lý riêng: phiên có thể còn dữ liệu để tra cứu trong admin dù link truy cập đã hết hạn.

## 6. Nội dung landing page

### Phần thông tin chiến dịch

- Tên chiến dịch nguồn.
- Trạng thái phiên.
- Thời gian link hết hạn.
- Tổng user đủ điều kiện.
- Số sponsor cần chọn.
- Số đã chọn và số còn lại.
- Chiến dịch nhận sponsor nếu admin đã chọn.

### Danh sách user đủ điều kiện

- Chỉ hiển thị tên người dùng.
- Có thể đánh dấu user đã trúng, nhưng không hiển thị thông tin cá nhân bổ sung.
- Danh sách và trạng thái được lấy từ backend.

### Khu vực kết quả

- Hiển thị từng lượt quay theo thứ tự.
- Chỉ hiển thị tên user trúng.
- Sau khi đủ số lượng, hiển thị trạng thái chờ admin xác nhận/gửi thông báo.
- Không có nút từ chối kết quả cho user.

### Trạng thái lỗi/truy cập

- Link hết hạn: hiển thị trang hết hạn, không tải dữ liệu phiên.
- Không thuộc chiến dịch: hiển thị thông báo không có quyền truy cập.
- Phiên đã kết thúc: hiển thị kết quả nếu link vẫn còn hạn và user vẫn đủ quyền.
- Phiên bị hủy: hiển thị trạng thái phiên đã bị hủy theo chính sách hiển thị.

## 7. Yêu cầu backend/API

Tên endpoint dưới đây chỉ mang tính gợi ý; agent cần tuân theo quy ước hiện có của dự án.

- Admin tạo/cập nhật cấu hình phiên.
- Admin phát hành link có token ngẫu nhiên đủ mạnh và thời gian hết hạn.
- Tải thông tin phiên cho người đã đăng nhập và được phép truy cập.
- Tải danh sách user đủ điều kiện, chỉ trả trường tên cần hiển thị.
- Admin thực hiện một lượt quay.
- Admin xác nhận kết quả và yêu cầu gửi Notify Gateway.
- Admin truy vấn trạng thái gửi thông báo và gửi lại các kênh lỗi.
- Admin xem lịch sử phiên, các lượt quay và audit log.

Mọi endpoint phải kiểm tra quyền ở backend. Không xem việc ẩn nút trên frontend là cơ chế phân quyền.

## 8. Mô hình dữ liệu gợi ý

Tùy cấu trúc dự án hiện tại, có thể dùng các bảng tương đương sau:

### `sponsor_draw_sessions`

- `id`
- `campaign_id`
- `target_campaign_id` nullable
- `target_winner_count`
- `status`
- `access_token_hash` hoặc cơ chế lưu token an toàn tương đương
- `link_expires_at`
- `eligibility_snapshot_at`
- `created_by`
- `confirmed_by` nullable
- `confirmed_at` nullable
- `created_at`, `updated_at`

### `sponsor_draw_eligible_users`

Snapshot các user đủ điều kiện tại thời điểm tạo/chốt phiên:

- `id`
- `session_id`
- `user_id`
- `display_name_snapshot`
- `is_winner`
- unique (`session_id`, `user_id`)

### `sponsor_draw_results`

- `id`
- `session_id`
- `user_id`
- `display_name_snapshot`
- `draw_order`
- `drawn_by`
- `drawn_at`
- unique (`session_id`, `user_id`)
- unique (`session_id`, `draw_order`)

### `sponsor_draw_notifications`

- `id`
- `session_id`
- `channel` (ví dụ `chatwork`, `telegram`)
- `status`
- `payload_snapshot`
- `provider_reference` nullable
- `attempt_count`
- `last_error` nullable
- `sent_at` nullable
- timestamps

### `sponsor_draw_audit_logs`

Ghi lại các thao tác quan trọng như tạo phiên, phát hành link, bắt đầu quay, từng lượt quay, điều chỉnh số lượng, hủy phiên, xác nhận kết quả, gửi lại thông báo.

Agent cần rà soát schema hiện tại trước khi tạo bảng mới; tái sử dụng campaign, user, notification gateway và audit log sẵn có nếu phù hợp.

## 9. Bảo mật và tính nhất quán

- Token link phải ngẫu nhiên, khó đoán; chỉ lưu hash nếu phù hợp với cơ chế xác thực hiện có.
- Kiểm tra hết hạn ở mọi request backend liên quan đến link, không chỉ khi render trang đầu tiên.
- Sau đăng nhập phải kiểm tra lại hạn link và thành viên chiến dịch.
- Không trả email, số điện thoại hoặc dữ liệu user ngoài tên hiển thị.
- Chỉ admin được gọi API quay/xác nhận/gửi; kiểm tra quyền và CSRF/auth theo chuẩn dự án.
- Dùng transaction/lock hoặc cơ chế tương đương khi quay để chống request đồng thời, trùng người thắng và vượt số lượng.
- Ràng buộc unique ở database để bảo vệ quy tắc mỗi user trúng một lần.
- Không để frontend gửi `user_id` người thắng để backend chấp nhận. Backend phải tự chọn người thắng.
- Chống gửi thông báo lặp bằng idempotency key hoặc unique constraint phù hợp.
- Ghi audit log cho kết quả và các thay đổi quan trọng.

## 10. Các trường hợp cần xử lý

- Chiến dịch không có user active: không cho phát hành phiên.
- Số lượng sponsor nhỏ hơn 1: báo lỗi validation.
- Số lượng sponsor lớn hơn số user đủ điều kiện: báo lỗi trước khi tạo hoặc không cho bắt đầu quay.
- User bị inactive hoặc rời chiến dịch sau khi snapshot: áp dụng danh sách snapshot đã chốt; agent cần bảo đảm chính sách này nhất quán. Mặc định đề xuất: snapshot là nguồn sự thật cho phiên đã phát hành.
- Hai admin thao tác quay gần như đồng thời: chỉ tạo một kết quả hợp lệ cho mỗi lượt.
- Admin refresh trang sau một lượt quay: kết quả đã ghi phải được tải lại, không quay lại tự động.
- Link hết hạn trong lúc người dùng đang mở trang: API tiếp theo phải trả trạng thái hết hạn; UI chuyển sang màn hình hết hạn và dừng thao tác.
- Notify Gateway lỗi một kênh: lưu lỗi, giữ kết quả, cho phép gửi lại riêng kênh lỗi.
- Admin bấm gửi nhiều lần: không gửi lặp kênh đã thành công.
- Phiên thiếu user đủ điều kiện do dữ liệu thay đổi: không tự chọn trùng để đủ số lượng.

## 11. Tiêu chí nghiệm thu

1. Admin tạo được phiên từ một chiến dịch và nhập số sponsor cần chọn.
2. Hệ thống chỉ lấy user thuộc chiến dịch và active theo quy tắc đã chốt.
3. Có thể bỏ qua chiến dịch nhận sponsor; nếu chọn thì được lưu và hiển thị đúng.
4. Landing page chỉ hiển thị tên người dùng, không lộ thông tin cá nhân khác.
5. Chỉ admin có thể quay; user thường chỉ xem.
6. User không thuộc chiến dịch không thể truy cập dữ liệu phiên.
7. Link truy cập được trong thời hạn cấu hình và bị từ chối sau khi hết hạn.
8. Sau khi hết hạn, link không còn truy cập được; dữ liệu phiên và kết quả vẫn còn trong admin.
9. Mỗi lượt chọn đúng một user chưa trúng; cùng một user không thể trúng hai lần trong phiên.
10. Số kết quả không vượt quá số lượng sponsor mục tiêu.
11. Backend quyết định user trúng; sửa request frontend không thể ép chọn user cụ thể.
12. Khi đủ số lượng, hệ thống khóa chức năng quay thêm.
13. Không có luồng user từ chối kết quả.
14. Chỉ sau khi admin xác nhận, hệ thống mới gửi thông báo.
15. Trạng thái gửi của từng kênh được lưu; có thể gửi lại kênh lỗi mà không gửi trùng kênh thành công.
16. Có audit log cho các thao tác quản trị và kết quả quay.
17. Có xử lý đúng các request đồng thời, link hết hạn, phiên rỗng, thiếu user và lỗi Notify Gateway.

## 12. Hướng dẫn cho coding agent

1. Khảo sát cấu trúc dự án, mô hình campaign/user, cách xác định trạng thái active, phân quyền admin, cách tạo link/token, frontend hiện có và Notify Gateway.
2. Đối chiếu yêu cầu này với quy ước dự án; không tạo lại dịch vụ đã có.
3. Đề xuất hoặc cập nhật migration, model, service, controller/API, policy/middleware, job gửi thông báo và giao diện.
4. Giữ logic chọn người thắng ở backend và đảm bảo transaction/ràng buộc dữ liệu.
5. Viết kiểm thử cho điều kiện active/thuộc chiến dịch, phân quyền, token hết hạn, chọn không trùng, giới hạn số lượng, xác nhận gửi, idempotency và retry từng kênh.
6. Chạy các kiểm thử liên quan và ghi lại lệnh/kết quả.
7. Báo cáo các file đã thay đổi, migration cần chạy, cấu hình gateway cần có và các giả định còn lại.

## 13. Giả định mặc định

- Thời hạn link mặc định là 1 giờ, nhưng nên cấu hình được ở admin hoặc config hệ thống.
- Danh sách đủ điều kiện được snapshot khi phiên được phát hành; thay đổi trạng thái user sau đó không làm thay đổi tập người tham gia của phiên.
- Kết quả phiên đã quay được lưu lâu dài theo chính sách lưu trữ của dự án, kể cả khi link hết hạn.
- User thuộc chiến dịch được phép xem landing page khi link còn hạn; chỉ admin có quyền thay đổi trạng thái hoặc quay.

## 14. Xem vòng quay realtime và kiểm chứng tính công bằng

### 14.1. Mục tiêu

Tất cả user đủ quyền đang mở landing page phải theo dõi cùng một phiên quay theo thời gian thực. Kết quả và tiến độ phải do backend phát ra, không phụ thuộc vào trạng thái riêng trên trình duyệt của admin. User có thể tải lại trang hoặc kết nối lại mà vẫn nhận được đúng trạng thái hiện tại và lịch sử sự kiện.

Realtime giúp mọi người quan sát cùng lúc, nhưng riêng hiệu ứng vòng quay không chứng minh được tính ngẫu nhiên. Vì vậy, cần khóa trước danh sách tham gia, lưu nhật ký bất biến theo phiên và cung cấp dữ liệu để kiểm tra lại cách chọn người thắng.

### 14.2. Trạng thái realtime cần phát

Dùng cơ chế realtime hiện có của dự án (WebSocket, Laravel Echo, SSE hoặc giải pháp tương đương). Nếu chưa có, agent đánh giá SSE cho luồng một chiều từ server đến người xem; các thao tác quay vẫn gửi qua API có xác thực.

Các sự kiện đề xuất:

- `session.ready`: phiên bắt đầu, thời hạn link, số sponsor mục tiêu.
- `eligibility.locked`: danh sách đủ điều kiện đã được chốt, tổng số user và mã hash snapshot.
- `draw.started`: lượt quay tiếp theo bắt đầu.
- `draw.completed`: kết quả lượt quay do backend tạo, gồm tên người trúng, số thứ tự và thời gian server.
- `session.completed`: đã chọn đủ số lượng.
- `result.confirmed`: admin đã xác nhận kết quả.
- `notification.updated`: trạng thái gửi theo từng kênh thay đổi.
- `session.cancelled` hoặc `session.expired`: phiên bị hủy hoặc link hết hạn.

Mỗi sự kiện cần có `session_id`, số thứ tự tăng dần (`sequence`), thời gian server và mã định danh sự kiện. Client phát hiện bị thiếu sequence thì gọi API tải lại trạng thái/lịch sử từ sequence gần nhất. Không tin timestamp hoặc kết quả do browser gửi lên.

### 14.3. Quy trình khóa danh sách và công bố bằng chứng

1. Khi phiên được phát hành, backend tạo snapshot bất biến từ các user thuộc chiến dịch và active theo quy tắc tại mục 2.
2. Danh sách được sắp xếp theo khóa ổn định nội bộ (ví dụ user ID), nhưng giao diện chỉ hiển thị tên. Backend tính hash snapshot từ dữ liệu chuẩn hóa, bao gồm định danh nội bộ và trạng thái đủ điều kiện; không đưa dữ liệu cá nhân vào hash công khai ngoài phạm vi cần thiết.
3. Trước lượt quay đầu tiên, hệ thống công bố mã hash snapshot, số lượng user đủ điều kiện, số sponsor mục tiêu và phương pháp chọn ngẫu nhiên. Không cho chỉnh danh sách hoặc số lượng sau khi đã bắt đầu quay. Nếu buộc phải sửa, phải hủy phiên cũ với lý do được ghi log và tạo phiên mới; không xóa lịch sử phiên cũ.
4. Trong khi quay, mỗi kết quả được ghi vào database trước rồi mới phát sự kiện realtime. Nếu phát realtime lỗi, client vẫn có thể tải lại kết quả đã lưu qua API.
5. Mọi thành viên đang xem thấy cùng số thứ tự lượt quay và cùng tên kết quả. Hiệu ứng animation chỉ là phần trình bày; dữ liệu kết quả chính thức lấy từ backend.

### 14.4. Mức độ kiểm chứng ngẫu nhiên

**Khuyến nghị cho độ tin cậy cao:** sử dụng nguồn ngẫu nhiên công khai, độc lập với admin và được xác định trước khi quay (public randomness beacon hoặc nguồn tương đương có thể kiểm tra chữ ký/tính xác thực). Sau khi snapshot đã khóa và hash đã công bố, backend lấy giá trị của vòng/pulse tiếp theo theo quy tắc đã cấu hình. Từ giá trị này, backend chạy thuật toán xác định để chọn user không trùng, ví dụ Fisher–Yates với nguồn byte đủ mạnh hoặc thuật toán tương đương tránh modulo bias. Công bố nguồn, mã round/pulse, giá trị ngẫu nhiên, phiên bản thuật toán và thứ tự snapshot để các bên có thể tính lại kết quả.

Nếu dự án chưa thể tích hợp nguồn ngẫu nhiên công khai ở MVP, có thể dùng CSPRNG phía backend kết hợp cam kết hash trước khi quay và công bố seed sau khi phiên kết thúc. Cách này giúp phát hiện việc thay seed sau khi công bố cam kết, nhưng mức đảm bảo thấp hơn nguồn entropy độc lập vì đơn vị vận hành hệ thống có thể tạo nhiều seed trước khi công bố. UI/tài liệu phải mô tả chính xác mức đảm bảo; không tuyên bố rằng realtime tự nó chứng minh kết quả ngẫu nhiên.

Không được âm thầm chuyển sang một nguồn ngẫu nhiên yếu hơn khi nguồn đã cấu hình bị lỗi. Phiên phải tạm dừng và ghi trạng thái/lý do; chỉ được tiếp tục theo phương án dự phòng đã được công khai trước khi bắt đầu, hoặc hủy phiên và lưu đầy đủ audit log.

### 14.5. Nhật ký sự kiện có thể phát hiện chỉnh sửa

- Ghi append-only các sự kiện quan trọng: snapshot đã khóa, từng lượt quay, xác nhận kết quả, hủy phiên, gửi thông báo.
- Mỗi bản ghi lưu hash của bản ghi trước (`previous_hash`) và hash của chính bản ghi hiện tại (`event_hash`) để phát hiện chỉnh sửa/xóa sau đó.
- Ghi admin thực hiện thao tác, thời điểm server, request/event ID và dữ liệu kết quả liên quan.
- Hạn chế quyền sửa/xóa audit log; quyền admin quay không được đồng nghĩa với quyền chỉnh sửa hoặc xóa log.
- Có trang quản trị tải biên bản kiểm chứng gồm snapshot/hash, cấu hình phương pháp random, dữ liệu beacon hoặc seed đã công bố, kết quả và chuỗi audit log.

Hash chain phát hiện thay đổi trong log nhưng không tự chứng minh log chưa bị viết lại toàn bộ. Với yêu cầu kiểm toán cao, nên định kỳ ký số hoặc lưu hash gốc ở một nơi độc lập/không cho admin phiên quay sửa.

### 14.6. Quyền xem và bảo vệ dữ liệu

- Chỉ user đã đăng nhập và thuộc chiến dịch mới được subscribe kênh realtime của phiên.
- Chỉ admin được phát lệnh quay; kênh realtime chỉ phát trạng thái/kết quả, không cho client ghi sự kiện.
- Kiểm tra quyền khi mở kết nối và khi kết nối lại; link hết hạn hoặc user mất quyền thì ngắt subscription và API phải từ chối tải thêm dữ liệu.
- Chỉ phát tên người dùng theo yêu cầu; không phát email, số điện thoại, user ID hoặc payload nhạy cảm qua WebSocket/SSE.
- Không dùng sự kiện từ trình duyệt admin làm bằng chứng kết quả. Backend là nguồn sự thật duy nhất.

### 14.7. Giao diện quan sát realtime

- Hiển thị trạng thái kết nối: `Đang kết nối`, `Đang theo dõi trực tiếp`, `Mất kết nối - đang đồng bộ lại`.
- Hiển thị tên admin đang thao tác và thời gian server của lượt quay; không cho phép user thường điều khiển vòng quay.
- Kết quả mới xuất hiện đồng thời cho mọi người xem; hiển thị rõ số lượt, tên người trúng và tổng tiến độ.
- Nếu mất kết nối, giữ kết quả đã nhận, tự kết nối lại và đồng bộ các sequence còn thiếu; không tự phát animation giả hoặc tạo lượt quay mới.
- Khi link hết hạn, dừng stream và chuyển tất cả người đang mở trang sang màn hình hết hạn.
- Kết thúc phiên, hiển thị nút/đường dẫn xem biên bản kiểm chứng nếu được bật.

### 14.8. Tiêu chí nghiệm thu bổ sung

1. Nhiều user đủ quyền mở cùng một phiên và nhận cùng thứ tự sự kiện/kết quả.
2. Chỉ admin có thể yêu cầu quay; user thường không thể phát lệnh quay qua API hay kênh realtime.
3. Kết quả được ghi vào backend/database trước khi phát sự kiện realtime.
4. Refresh hoặc reconnect không làm mất, nhân đôi hoặc đảo thứ tự kết quả; client đồng bộ được sự kiện thiếu theo sequence.
5. Danh sách và số lượng mục tiêu bị khóa sau khi bắt đầu quay; thay đổi phải tạo phiên mới hoặc đi qua quy trình hủy có audit log.
6. Có thể xác minh snapshot khớp với hash đã công bố.
7. Nếu dùng public randomness beacon, người kiểm tra độc lập có đủ thông tin để chạy lại thuật toán và thu được cùng danh sách người thắng.
8. Nếu dùng seed cam kết trong MVP, seed chỉ được công bố sau phiên; kiểm tra được seed khớp commitment đã phát trước lượt quay đầu tiên.
9. Không tuyên bố mức độ công bằng vượt quá khả năng kiểm chứng thực tế của nguồn random đang dùng.
10. Lỗi realtime không làm mất kết quả đã lưu; lỗi nguồn random không dẫn đến fallback âm thầm.
11. Link hết hạn hoặc mất quyền làm ngắt stream và ngăn truy cập tiếp theo.
12. Có kiểm thử cho nhiều người xem, request quay đồng thời, reconnect thiếu sequence, sửa snapshot, hết hạn giữa phiên và lỗi nguồn random.

## 15. Stack đề xuất và tái sử dụng open source

### 15.1. Kết luận

Nên triển khai chức năng ngay trong ứng dụng Laravel 13 hiện tại, dùng lại bảng/model campaign, user, trạng thái active, đăng nhập, phân quyền và Notify Gateway đang có. Không cần triển khai thêm một ứng dụng raffle riêng rồi đồng bộ user/campaign qua API.

Có thể tận dụng các open source component cho realtime và kiểm chứng nguồn ngẫu nhiên. Chưa chọn một ứng dụng quay số hoàn chỉnh làm lõi vì nghiệp vụ này cần ràng buộc trực tiếp với quyền admin, thành viên campaign, snapshot user active, link hết hạn, kết quả và gateway hiện hữu. Một app độc lập sẽ phải đồng bộ dữ liệu và phân quyền, tạo thêm nguồn sự thật cần đối chiếu.

### 15.2. Stack khuyến nghị

| Lớp | Công nghệ | Vai trò |
|---|---|---|
| Backend/API | Laravel 13 hiện có | Xác thực, phân quyền, đọc campaign/user, kiểm tra active, tạo phiên, chọn kết quả, lưu audit và gọi Notify Gateway. |
| Database | MySQL hiện có | Nguồn dữ liệu chính cho campaign, user, snapshot đủ điều kiện, phiên quay, kết quả, event log và trạng thái gửi thông báo. |
| Realtime server | Laravel Reverb | WebSocket server open source, tích hợp trực tiếp Laravel Broadcasting. Dùng một instance cho MVP; chỉ mở rộng nhiều instance khi có nhu cầu tải thực tế. |
| Broadcast client | Laravel Echo + `pusher-js` | Subscribe sự kiện Reverb trong trình duyệt. Echo/Reverb tương thích Pusher protocol. |
| UI | Blade hiện có + JavaScript/Vite hiện có | Blade render khung giao diện; JS/Echo nhận event và chạy hiệu ứng. Không cần chuyển sang React/Vue hoặc viết lại frontend thành SPA. |
| Kênh | Laravel private channel | Ví dụ `private-draw-session.{sessionId}`. Chỉ cho user đã đăng nhập và là thành viên campaign, đồng thời link/phiên chưa hết hạn. Không dùng public channel. |
| Queue | Laravel Queue; ưu tiên Redis nếu dự án đã có, nếu chưa có có thể dùng database queue cho tải nhỏ | Xử lý broadcast và gửi thông báo bất đồng bộ. Worker chạy bằng Supervisor/systemd. Broadcast phải dispatch sau khi transaction MySQL commit. |
| Proxy/process | Nginx WebSocket proxy + Supervisor/systemd | Chuyển WSS về Reverb và giữ tiến trình Reverb/queue worker luôn chạy. |
| Nguồn random có thể kiểm chứng | drand beacon + `drand-client` để xác minh beacon phía trình duyệt | Tạo bằng chứng độc lập về entropy công khai. Server và client cùng tính lại thứ tự người thắng từ snapshot + beacon + thuật toán đã công bố. |
| Kiểm thử | PHPUnit/Pest hiện có | Kiểm tra authorization channel, broadcast sau commit, draw đồng thời, reconnect, hết hạn link, thuật toán shuffle và idempotency thông báo. |

Laravel 13 có lệnh `php artisan install:broadcasting --reverb` để cài cấu hình Reverb và Echo; docs yêu cầu chạy queue worker cho broadcasting. Laravel Echo nhận event từ các broadcaster; private channel được Laravel authorize bằng HTTP request đến ứng dụng. citeturn3view0 Reverb là dự án open source MIT do Laravel duy trì. citeturn3view1

### 15.3. Tích hợp realtime với dữ liệu hệ thống hiện tại

1. Blade gọi API lấy trạng thái phiên và dữ liệu hiển thị theo quyền hiện tại.
2. Blade khởi tạo Laravel Echo từ bundle Vite rồi subscribe private channel của phiên.
3. Callback authorize channel kiểm tra user đăng nhập, user thuộc campaign nguồn, link còn hạn và phiên được phép xem.
4. Admin gửi lệnh quay đến Laravel API. Client không tự chọn hoặc gửi `winner_user_id`.
5. Laravel transaction khóa phiên, lấy snapshot đã chốt, chọn kết quả chưa trúng, ghi kết quả và event sequence vào MySQL.
6. Sau khi transaction commit, Laravel phát broadcast event qua queue/Reverb.
7. Tất cả user đang kết nối nhận cùng event; JavaScript chỉ chạy animation và cập nhật giao diện theo dữ liệu do server gửi.
8. Khi client refresh hoặc reconnect, gọi API tải trạng thái và event lịch sử sau sequence cuối cùng đã nhận; database vẫn là nguồn sự thật.
9. Notify Gateway chạy riêng qua queue sau khi admin xác nhận; lỗi gửi thông báo không làm thay đổi kết quả quay.

### 15.4. Phương án random minh bạch với drand

Để hạn chế admin chọn seed hoặc quay lại nhiều lần cho đến khi có kết quả mong muốn, nên khóa và công bố snapshot trước khi sử dụng một beacon round ở tương lai:

1. Chốt danh sách đủ điều kiện, số sponsor cần chọn, thuật toán và quy tắc chọn beacon round.
2. Tính và công bố hash snapshot, thời gian khóa, tên mạng/chain và round đích. Round đích phải được xác định bằng quy tắc công khai trước khi giá trị random được sinh ra; không cho admin tự chọn round sau khi thấy kết quả.
3. Khi beacon round đã có, lấy randomness đã xác minh. drand mô tả randomness của họ là phi tập trung, công khai, có thể kiểm chứng và không thiên lệch; thư viện `drand-client` có tùy chọn bật kiểm tra chữ ký beacon. citeturn3view2turn3view3
4. Tạo seed phiên từ dữ liệu đã công bố, ví dụ `SHA-256(beacon_randomness || session_id || eligibility_snapshot_hash || algorithm_version)`.
5. Dùng thuật toán deterministic Fisher–Yates có xử lý modulo bias để xáo trộn snapshot và lấy đúng N user đầu tiên. Backend lưu kết quả; frontend có thể chạy cùng phép tính để kiểm tra kết quả sau khi công bố.
6. Hiệu ứng realtime lần lượt tiết lộ các user theo thứ tự đã xác định. Không tạo một lần random mới cho mỗi animation hoặc mỗi client.
7. Lưu chain hash, round, signature/beacon payload, snapshot hash, algorithm version và thứ tự kết quả để kiểm toán.
8. Nếu beacon chưa sẵn sàng, hệ thống tiếp tục chờ đúng round đã định; không tự chuyển sang seed khác. Nếu round không thể lấy/xác minh được theo thời hạn chính sách, dừng phiên và ghi log hoặc tạo phiên mới với snapshot/quy tắc mới được công bố.

Đây là mức xác minh độc lập tốt hơn CSPRNG riêng của server. `drand-client` là thư viện JavaScript; nếu xác minh cả ở backend PHP, agent cần chọn thư viện PHP BLS đã được kiểm tra hoặc xây adapter phù hợp và có test vector. Không được chỉ tin giá trị do một relay trả về mà bỏ qua kiểm tra chain/round/signature.

Nếu chưa muốn thêm dependency drand cho MVP, có thể dùng PHP `random_int()`/CSPRNG backend với commit-reveal và audit log. Nhưng cần ghi rõ đó là kiểm chứng hạn chế hơn; không được tuyên bố kết quả có thể được bên thứ ba xác minh tương đương public beacon.

### 15.5. Phạm vi không cần thêm

- Không cần Kafka, microservice riêng, frontend framework mới hoặc đồng bộ user sang database khác cho quy mô phiên quay thông thường.
- Không cần chuyển toàn bộ campaign/user sang một ứng dụng raffle bên ngoài.
- Redis cho Reverb horizontal scaling chỉ cần khi chạy nhiều Reverb instance; Redis Queue là lựa chọn vận hành, không phải điều kiện bắt buộc cho MySQL hiện có.
- Không dùng một package turntable cũ làm logic chọn người thắng nếu không có kiểm tra tương thích Laravel 13, license, hoạt động bảo trì, cơ chế chống trùng và bảo mật. Hiệu ứng vòng quay có thể viết bằng CSS/JS nhỏ; phần quan trọng là thuật toán chọn và dữ liệu backend.

### 15.6. Việc coding agent cần xác minh trong source hiện tại

- Phiên bản PHP thực tế đáp ứng Laravel 13 và yêu cầu của Reverb.
- Dự án đã có Laravel Broadcasting, Echo, Vite, Redis, queue worker, Supervisor/systemd và Nginx WebSocket config hay chưa; tái sử dụng cái đang có.
- Tên bảng/model/quan hệ campaign-user và cột xác định active chính xác.
- Cơ chế auth đang dùng ở route web/API; đảm bảo private channel authorization dùng cùng guard phù hợp với Blade session.
- Mức tải dự kiến, số người xem đồng thời và cấu hình timeout/proxy để chọn queue, ping interval và giới hạn kết nối phù hợp.
- License, phiên bản, hoạt động bảo trì và test vector của bất kỳ package random/BLS nào trước khi thêm.
