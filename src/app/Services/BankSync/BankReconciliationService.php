<?php

declare(strict_types=1);

namespace App\Services\BankSync;

use App\Actions\Debt\ApproveDebtPaymentAction;
use App\Actions\Debt\ApproveDebtPaymentRequestAction;
use App\Actions\Debt\RecordDebtPaymentAction;
use App\Enums\BankTransactionStatus;
use App\Enums\DebtStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\PaymentMethod;
use App\Models\Admin;
use App\Models\BankTransaction;
use App\Models\Debt;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomBankIntegration;
use App\Models\RoomUser;
use App\Services\Audit\AuditService;
use App\Services\Payment\VietQrService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Matches incoming bank transfers of a room to what members owe.
 *
 * A transfer is settled automatically only on an exact match:
 *  - its content contains the code of exactly one outstanding debt and the amount equals that debt's balance; or
 *  - its content carries a member reference (TV{id}, the pay-all QR) and the amount equals that member's pending
 *    payment request, or else the total of the member's payable debts.
 * Anything else stays `unmatched` for the room admin, who assigns it to a debt or dismisses it.
 *
 * Every provider transaction is stored once (unique index), so a retried webhook never settles twice.
 */
class BankReconciliationService
{
    public const REASON_OUTGOING = 'outgoing';
    public const REASON_OTHER_ACCOUNT = 'other_account';
    public const REASON_NO_REFERENCE = 'no_reference';
    public const REASON_AMBIGUOUS = 'ambiguous';
    public const REASON_AMOUNT_MISMATCH = 'amount_mismatch';
    public const REASON_NEEDS_REVIEW = 'needs_review';
    public const REASON_DEBT = 'debt';
    public const REASON_PAYMENT_REQUEST = 'payment_request';
    public const REASON_PAY_ALL = 'pay_all';
    public const REASON_DISMISSED = 'dismissed';

    public function __construct(
        private readonly VietQrService $vietQr,
        private readonly ApproveDebtPaymentAction $approveDebt,
        private readonly ApproveDebtPaymentRequestAction $approveRequest,
        private readonly RecordDebtPaymentAction $recordPayment,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Store a transaction reported by the room's bank feed and reconcile it. A replayed notification returns the
     * transaction recorded the first time, untouched.
     *
     * @param RoomBankIntegration $integration Room connection that received it.
     * @param BankTransferData $data Parsed transaction.
     * @return BankTransaction Stored (and reconciled) transaction.
     */
    public function ingest(RoomBankIntegration $integration, BankTransferData $data): BankTransaction
    {
        $provider = $integration->provider;
        try {
            $transaction = DB::transaction(static fn (): BankTransaction => BankTransaction::create([
                'room_id' => $integration->room_id,
                'provider' => $provider,
                'provider_transaction_id' => $data->transactionId,
                'account_number' => $data->accountNumber,
                'direction' => $data->direction,
                'amount' => $data->amount,
                'content' => $data->content,
                'reference_code' => $data->referenceCode,
                'transacted_at' => $data->transactedAt,
                'status' => BankTransactionStatus::Unmatched,
                'payload' => $data->payload,
            ]));
        } catch (UniqueConstraintViolationException) {
            return BankTransaction::query()->where('provider', $provider->value)->where('provider_transaction_id', $data->transactionId)->firstOrFail();
        }

        $integration->forceFill(['last_received_at' => now()])->save();

        return $this->reconcile($transaction);
    }

    /**
     * Try to settle an unmatched transaction automatically.
     *
     * @param BankTransaction $transaction Stored transaction.
     * @return BankTransaction The transaction with its new status.
     */
    public function reconcile(BankTransaction $transaction): BankTransaction
    {
        $room = Room::query()->findOrFail($transaction->room_id);
        [$status, $reason, $links] = $this->decide($room, $transaction);
        $transaction->forceFill(['status' => $status, 'reason' => $reason] + $links)->save();

        return $transaction;
    }

    /**
     * Admin assignment of an unmatched transfer to a debt: the full balance settles it, a smaller amount is recorded
     * as a partial payment.
     *
     * @param BankTransaction $transaction Unmatched transaction of the room.
     * @param Room $room Current room.
     * @param string $debtCode Code of the debt it pays.
     * @param Admin|null $admin Admin assigning it.
     * @return BankTransaction Resolved transaction.
     *
     * @throws ValidationException When the transaction is already resolved, the debt is not payable, or the amount
     *                             exceeds the debt's balance.
     */
    public function assign(BankTransaction $transaction, Room $room, string $debtCode, ?Admin $admin): BankTransaction
    {
        return DB::transaction(function () use ($transaction, $room, $debtCode, $admin): BankTransaction {
            $locked = $this->lockUnmatched($transaction, $room);
            $debt = $this->payableDebts($room)->where('code', trim($debtCode))->first();
            if ($debt === null) {
                throw ValidationException::withMessages(['debt_code' => __('bank_sync.debt_not_payable')]);
            }
            $amount = (int) $locked->amount;
            if ($amount > (int) $debt->remaining_amount) {
                throw ValidationException::withMessages(['debt_code' => __('bank_sync.amount_exceeds_debt')]);
            }

            if ($amount === (int) $debt->remaining_amount) {
                $this->approveDebt->execute($debt, $this->reference($locked));
            } else {
                $this->recordPayment->execute($debt, $amount, PaymentMethod::Transfer->value, $this->reference($locked));
            }

            $locked->forceFill([
                'status' => BankTransactionStatus::Manual,
                'reason' => self::REASON_DEBT,
                'debt_id' => $debt->id,
                'room_user_id' => $debt->room_user_id,
                'resolved_by_admin_id' => $admin?->id,
                'resolved_at' => now(),
            ])->save();
            $this->audit->record('bank_transaction.assigned', 'bank_transaction', $locked->id, $room->id, [], ['debt_id' => $debt->id, 'amount' => $amount]);

            return $locked;
        });
    }

    /**
     * Dismiss an unmatched transaction (not a member payment).
     *
     * @param BankTransaction $transaction Unmatched transaction of the room.
     * @param Room $room Current room.
     * @param Admin|null $admin Admin dismissing it.
     * @return BankTransaction Ignored transaction.
     *
     * @throws ValidationException When the transaction is already resolved.
     */
    public function ignore(BankTransaction $transaction, Room $room, ?Admin $admin): BankTransaction
    {
        return DB::transaction(function () use ($transaction, $room, $admin): BankTransaction {
            $locked = $this->lockUnmatched($transaction, $room);
            $locked->forceFill([
                'status' => BankTransactionStatus::Ignored,
                'reason' => self::REASON_DISMISSED,
                'resolved_by_admin_id' => $admin?->id,
                'resolved_at' => now(),
            ])->save();
            $this->audit->record('bank_transaction.ignored', 'bank_transaction', $locked->id, $room->id);

            return $locked;
        });
    }

    /**
     * Decide the status of a transaction, settling the matched debt or request on the way.
     *
     * @return array{0: BankTransactionStatus, 1: string, 2: array<string, int|null>} Status, reason and links.
     */
    private function decide(Room $room, BankTransaction $transaction): array
    {
        if ($transaction->direction !== BankTransaction::DIRECTION_IN || (int) $transaction->amount <= 0) {
            return [BankTransactionStatus::Ignored, self::REASON_OUTGOING, []];
        }
        if (! $this->isRoomAccount($room, $transaction->account_number)) {
            return [BankTransactionStatus::Ignored, self::REASON_OTHER_ACCOUNT, []];
        }

        $amount = (int) $transaction->amount;
        $content = (string) $transaction->content;
        $reference = $this->reference($transaction);

        $debts = $this->debtsNamedIn($room, $content);
        if ($debts->count() > 1) {
            return [BankTransactionStatus::Unmatched, self::REASON_AMBIGUOUS, []];
        }
        if ($debts->count() === 1) {
            /** @var Debt $debt */
            $debt = $debts->first();
            $links = ['debt_id' => $debt->id, 'room_user_id' => $debt->room_user_id];
            if ($amount !== (int) $debt->remaining_amount) {
                return [BankTransactionStatus::Unmatched, self::REASON_AMOUNT_MISMATCH, $links];
            }
            // A legacy pay-all confirmation (content = member UUID) would settle every pending debt of the member.
            if ($debt->payment_content !== null && $debt->payment_content === $debt->roomUser?->user_code) {
                return [BankTransactionStatus::Unmatched, self::REASON_NEEDS_REVIEW, $links];
            }

            return $this->settle(fn () => $this->approveDebt->execute($debt, $reference), self::REASON_DEBT, $links);
        }

        $memberId = $this->vietQr->memberReferenceIn($content);
        $member = $memberId !== null ? RoomUser::query()->where('room_id', $room->id)->whereKey($memberId)->first() : null;
        if ($member === null) {
            return [BankTransactionStatus::Unmatched, self::REASON_NO_REFERENCE, []];
        }
        $links = ['room_user_id' => $member->id];

        $request = Debt::paymentRequests()
            ->where('room_id', $room->id)
            ->where('room_user_id', $member->id)
            ->where('status', DebtStatus::Pending->value)
            ->where('original_amount', $amount)
            ->orderBy('id')
            ->first();
        if ($request !== null) {
            return $this->settle(fn () => $this->approveRequest->execute($room, $request->id, null), self::REASON_PAYMENT_REQUEST, $links + ['payment_request_id' => $request->id]);
        }

        $payable = $this->payableDebts($room)
            ->where('room_user_id', $member->id)
            ->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])
            ->orderBy('id')
            ->get();
        if ($payable->isEmpty() || (int) $payable->sum('remaining_amount') !== $amount) {
            return [BankTransactionStatus::Unmatched, self::REASON_AMOUNT_MISMATCH, $links];
        }

        return $this->settle(function () use ($payable, $reference): void {
            foreach ($payable as $debt) {
                $this->approveDebt->execute($debt, $reference);
            }
        }, self::REASON_PAY_ALL, $links);
    }

    /**
     * Run a settlement atomically; a failure (the debts changed meanwhile) leaves the transfer for the admin.
     *
     * @param callable(): mixed $settlement Settlement work.
     * @param array<string, int|null> $links Linked debt / request / member.
     * @return array{0: BankTransactionStatus, 1: string, 2: array<string, int|null>}
     */
    private function settle(callable $settlement, string $reason, array $links): array
    {
        try {
            DB::transaction(static fn () => $settlement());
        } catch (ValidationException|HttpException) {
            return [BankTransactionStatus::Unmatched, self::REASON_NEEDS_REVIEW, $links];
        }

        return [BankTransactionStatus::Matched, $reason, $links];
    }

    /**
     * Outstanding campaign debts that can be paid on their own (not frozen by a pending / approved request).
     *
     * @return Builder<Debt>
     */
    private function payableDebts(Room $room): Builder
    {
        return Debt::query()
            ->where('room_id', $room->id)
            ->whereIn('status', DebtStatus::outstandingValues())
            ->where('remaining_amount', '>', 0)
            ->notLockedByPaymentRequest();
    }

    /**
     * Payable debts whose code appears in the transfer content (compared on letters and digits only, since banks
     * often drop spaces and punctuation).
     *
     * @return Collection<int, Debt>
     */
    private function debtsNamedIn(Room $room, string $content): Collection
    {
        $normalized = $this->normalize($content);
        if ($normalized === '') {
            return collect();
        }

        return $this->payableDebts($room)->with('roomUser')->get()
            ->filter(fn (Debt $debt): bool => ($code = $this->normalize((string) $debt->code)) !== '' && str_contains($normalized, $code))
            ->values();
    }

    /**
     * Whether the receiving account is one of the room's accounts (any account passes when none is configured or the
     * provider did not say).
     */
    private function isRoomAccount(Room $room, ?string $accountNumber): bool
    {
        $number = $this->normalize((string) $accountNumber);
        if ($number === '') {
            return true;
        }
        $accounts = PaymentAccount::query()->where('room_id', $room->id)->where('status', PaymentAccountStatus::Active->value)->pluck('account_number')
            ->map(fn (?string $value): string => $this->normalize((string) $value))->filter();

        return $accounts->isEmpty() || $accounts->contains($number);
    }

    /**
     * Lock an unmatched transaction of the room for an admin decision.
     *
     * @throws ValidationException When it is already resolved.
     */
    private function lockUnmatched(BankTransaction $transaction, Room $room): BankTransaction
    {
        $locked = BankTransaction::query()->where('room_id', $room->id)->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
        if ($locked->status !== BankTransactionStatus::Unmatched) {
            throw ValidationException::withMessages(['transaction' => __('bank_sync.already_resolved')]);
        }

        return $locked;
    }

    private function reference(BankTransaction $transaction): string
    {
        return mb_substr($transaction->provider->name.' '.($transaction->reference_code ?: $transaction->provider_transaction_id), 0, 120);
    }

    private function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }
}
