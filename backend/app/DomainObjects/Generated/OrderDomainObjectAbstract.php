<?php

namespace HiEvents\DomainObjects\Generated;

/**
 * THIS FILE IS AUTOGENERATED - DO NOT EDIT IT DIRECTLY.
 * @package HiEvents\DomainObjects\Generated
 */
abstract class OrderDomainObjectAbstract extends \HiEvents\DomainObjects\AbstractDomainObject
{
    final public const SINGULAR_NAME = 'order';
    final public const PLURAL_NAME = 'orders';
    final public const ID = 'id';
    final public const EVENT_ID = 'event_id';
    final public const PROMO_CODE_ID = 'promo_code_id';
    final public const SHORT_ID = 'short_id';
    final public const TOTAL_BEFORE_ADDITIONS = 'total_before_additions';
    final public const TOTAL_REFUNDED = 'total_refunded';
    final public const TOTAL_GROSS = 'total_gross';
    final public const CURRENCY = 'currency';
    final public const FIRST_NAME = 'first_name';
    final public const LAST_NAME = 'last_name';
    final public const EMAIL = 'email';
    final public const STATUS = 'status';
    final public const PAYMENT_STATUS = 'payment_status';
    final public const REFUND_STATUS = 'refund_status';
    final public const RESERVED_UNTIL = 'reserved_until';
    final public const IS_MANUALLY_CREATED = 'is_manually_created';
    final public const SESSION_ID = 'session_id';
    final public const PUBLIC_ID = 'public_id';
    final public const POINT_IN_TIME_DATA = 'point_in_time_data';
    final public const PAYMENT_GATEWAY = 'payment_gateway';
    final public const PROMO_CODE = 'promo_code';
    final public const ADDRESS = 'address';
    final public const CREATED_AT = 'created_at';
    final public const UPDATED_AT = 'updated_at';
    final public const DELETED_AT = 'deleted_at';
    final public const TAXES_AND_FEES_ROLLUP = 'taxes_and_fees_rollup';
    final public const TOTAL_TAX = 'total_tax';
    final public const TOTAL_FEE = 'total_fee';
    final public const LOCALE = 'locale';
    final public const PAYMENT_PROVIDER = 'payment_provider';
    final public const NOTES = 'notes';

    protected int $id;
    protected int $event_id;
    protected ?int $promo_code_id = null;
    protected string $short_id;
    protected float $total_before_additions = 0.0;
    protected float $total_refunded = 0.0;
    protected float $total_gross = 0.0;
    protected string $currency;
    protected ?string $first_name = null;
    protected ?string $last_name = null;
    protected ?string $email = null;
    protected string $status;
    protected ?string $payment_status = null;
    protected ?string $refund_status = null;
    protected ?string $reserved_until = null;
    protected bool $is_manually_created = false;
    protected ?string $session_id = null;
    protected string $public_id;
    protected array|string|null $point_in_time_data = null;
    protected ?string $payment_gateway = null;
    protected ?string $promo_code = null;
    protected array|string|null $address = null;
    protected string $created_at;
    protected ?string $updated_at = null;
    protected ?string $deleted_at = null;
    protected array|string|null $taxes_and_fees_rollup = null;
    protected float $total_tax = 0.0;
    protected float $total_fee = 0.0;
    protected string $locale = 'en';
    protected ?string $payment_provider = null;
    protected ?string $notes = null;

    public function toArray(): array
    {
        return [
                    'id' => $this->id ?? null,
                    'event_id' => $this->event_id ?? null,
                    'promo_code_id' => $this->promo_code_id ?? null,
                    'short_id' => $this->short_id ?? null,
                    'total_before_additions' => $this->total_before_additions ?? null,
                    'total_refunded' => $this->total_refunded ?? null,
                    'total_gross' => $this->total_gross ?? null,
                    'currency' => $this->currency ?? null,
                    'first_name' => $this->first_name ?? null,
                    'last_name' => $this->last_name ?? null,
                    'email' => $this->email ?? null,
                    'status' => $this->status ?? null,
                    'payment_status' => $this->payment_status ?? null,
                    'refund_status' => $this->refund_status ?? null,
                    'reserved_until' => $this->reserved_until ?? null,
                    'is_manually_created' => $this->is_manually_created ?? null,
                    'session_id' => $this->session_id ?? null,
                    'public_id' => $this->public_id ?? null,
                    'point_in_time_data' => $this->point_in_time_data ?? null,
                    'payment_gateway' => $this->payment_gateway ?? null,
                    'promo_code' => $this->promo_code ?? null,
                    'address' => $this->address ?? null,
                    'created_at' => $this->created_at ?? null,
                    'updated_at' => $this->updated_at ?? null,
                    'deleted_at' => $this->deleted_at ?? null,
                    'taxes_and_fees_rollup' => $this->taxes_and_fees_rollup ?? null,
                    'total_tax' => $this->total_tax ?? null,
                    'total_fee' => $this->total_fee ?? null,
                    'locale' => $this->locale ?? null,
                    'payment_provider' => $this->payment_provider ?? null,
                    'notes' => $this->notes ?? null,
                ];
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setEventId(int $event_id): self
    {
        $this->event_id = $event_id;
        return $this;
    }

    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function setPromoCodeId(?int $promo_code_id): self
    {
        $this->promo_code_id = $promo_code_id;
        return $this;
    }

    public function getPromoCodeId(): ?int
    {
        return $this->promo_code_id;
    }

    public function setShortId(string $short_id): self
    {
        $this->short_id = $short_id;
        return $this;
    }

    public function getShortId(): string
    {
        return $this->short_id;
    }

    public function setTotalBeforeAdditions(float $total_before_additions): self
    {
        $this->total_before_additions = $total_before_additions;
        return $this;
    }

    public function getTotalBeforeAdditions(): float
    {
        return $this->total_before_additions;
    }

    public function setTotalRefunded(float $total_refunded): self
    {
        $this->total_refunded = $total_refunded;
        return $this;
    }

    public function getTotalRefunded(): float
    {
        return $this->total_refunded;
    }

    public function setTotalGross(float $total_gross): self
    {
        $this->total_gross = $total_gross;
        return $this;
    }

    public function getTotalGross(): float
    {
        return $this->total_gross;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setFirstName(?string $first_name): self
    {
        $this->first_name = $first_name;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function setLastName(?string $last_name): self
    {
        $this->last_name = $last_name;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setPaymentStatus(?string $payment_status): self
    {
        $this->payment_status = $payment_status;
        return $this;
    }

    public function getPaymentStatus(): ?string
    {
        return $this->payment_status;
    }

    public function setRefundStatus(?string $refund_status): self
    {
        $this->refund_status = $refund_status;
        return $this;
    }

    public function getRefundStatus(): ?string
    {
        return $this->refund_status;
    }

    public function setReservedUntil(?string $reserved_until): self
    {
        $this->reserved_until = $reserved_until;
        return $this;
    }

    public function getReservedUntil(): ?string
    {
        return $this->reserved_until;
    }

    public function setIsManuallyCreated(bool $is_manually_created): self
    {
        $this->is_manually_created = $is_manually_created;
        return $this;
    }

    public function getIsManuallyCreated(): bool
    {
        return $this->is_manually_created;
    }

    public function setSessionId(?string $session_id): self
    {
        $this->session_id = $session_id;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->session_id;
    }

    public function setPublicId(string $public_id): self
    {
        $this->public_id = $public_id;
        return $this;
    }

    public function getPublicId(): string
    {
        return $this->public_id;
    }

    public function setPointInTimeData(array|string|null $point_in_time_data): self
    {
        $this->point_in_time_data = $point_in_time_data;
        return $this;
    }

    public function getPointInTimeData(): array|string|null
    {
        return $this->point_in_time_data;
    }

    public function setPaymentGateway(?string $payment_gateway): self
    {
        $this->payment_gateway = $payment_gateway;
        return $this;
    }

    public function getPaymentGateway(): ?string
    {
        return $this->payment_gateway;
    }

    public function setPromoCode(?string $promo_code): self
    {
        $this->promo_code = $promo_code;
        return $this;
    }

    public function getPromoCode(): ?string
    {
        return $this->promo_code;
    }

    public function setAddress(array|string|null $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getAddress(): array|string|null
    {
        return $this->address;
    }

    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updated_at;
    }

    public function setDeletedAt(?string $deleted_at): self
    {
        $this->deleted_at = $deleted_at;
        return $this;
    }

    public function getDeletedAt(): ?string
    {
        return $this->deleted_at;
    }

    public function setTaxesAndFeesRollup(array|string|null $taxes_and_fees_rollup): self
    {
        $this->taxes_and_fees_rollup = $taxes_and_fees_rollup;
        return $this;
    }

    public function getTaxesAndFeesRollup(): array|string|null
    {
        return $this->taxes_and_fees_rollup;
    }

    public function setTotalTax(float $total_tax): self
    {
        $this->total_tax = $total_tax;
        return $this;
    }

    public function getTotalTax(): float
    {
        return $this->total_tax;
    }

    public function setTotalFee(float $total_fee): self
    {
        $this->total_fee = $total_fee;
        return $this;
    }

    public function getTotalFee(): float
    {
        return $this->total_fee;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setPaymentProvider(?string $payment_provider): self
    {
        $this->payment_provider = $payment_provider;
        return $this;
    }

    public function getPaymentProvider(): ?string
    {
        return $this->payment_provider;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }
}
