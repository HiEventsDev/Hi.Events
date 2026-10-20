<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail;

use HiEvents\DomainObjects\Enums\LifecycleNudgeType;
use HiEvents\DomainObjects\Generated\LifecycleEmailTemplateDomainObjectAbstract;
use HiEvents\DomainObjects\LifecycleEmailTemplateDomainObject;
use HiEvents\Exceptions\EmailTemplateValidationException;
use HiEvents\Mail\Lifecycle\LifecycleNudgeMail;
use HiEvents\Repository\Interfaces\LifecycleEmailTemplateRepositoryInterface;
use HiEvents\Services\Domain\Email\EmailContextHtmlEscaper;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailRecipientDTO;
use HiEvents\Services\Domain\LifecycleEmail\DTO\LifecycleEmailTemplateDTO;
use HiEvents\Services\Domain\LifecycleEmail\Nudges\LifecycleNudgeRegistry;
use HiEvents\Services\Infrastructure\Email\LiquidTemplateRenderer;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;
use Illuminate\Support\Collection;
use RuntimeException;

class LifecycleEmailTemplateService
{
    public function __construct(
        private readonly LifecycleEmailTemplateRepositoryInterface $templateRepository,
        private readonly LifecycleNudgeRegistry $nudgeRegistry,
        private readonly LiquidTemplateRenderer $liquidRenderer,
        private readonly EmailContextHtmlEscaper $contextHtmlEscaper,
        private readonly HtmlPurifierService $purifier,
        private readonly LifecycleEmailUnsubscribeTokens $unsubscribeTokens,
    ) {}

    /**
     * @return Collection<int, LifecycleEmailTemplateDTO>
     */
    public function findAll(): Collection
    {
        $saved = $this->templateRepository->all()
            ->keyBy(fn (LifecycleEmailTemplateDomainObject $template) => $template->getNudgeType());

        return collect(LifecycleNudgeType::cases())
            ->map(fn (LifecycleNudgeType $type) => $this->toDTO($type, $saved->get($type->name)));
    }

    public function find(LifecycleNudgeType $nudgeType): LifecycleEmailTemplateDTO
    {
        return $this->toDTO($nudgeType, $this->templateRepository->findFirstWhere([
            LifecycleEmailTemplateDomainObjectAbstract::NUDGE_TYPE => $nudgeType->name,
        ]));
    }

    /**
     * @throws EmailTemplateValidationException
     */
    public function save(
        LifecycleNudgeType $nudgeType,
        string $subject,
        string $body,
        string $ctaLabel,
        bool $enabled,
    ): LifecycleEmailTemplateDTO {
        $this->buildMail(
            new LifecycleEmailTemplateDTO(nudgeType: $nudgeType, subject: $subject, body: $body, ctaLabel: $ctaLabel),
            $this->nudgeRegistry->for($nudgeType)->sampleRecipient(),
        );

        $existing = $this->find($nudgeType);

        $attributes = [
            LifecycleEmailTemplateDomainObjectAbstract::SUBJECT => $subject,
            LifecycleEmailTemplateDomainObjectAbstract::BODY => $this->purifier->purify($body),
            LifecycleEmailTemplateDomainObjectAbstract::CTA_LABEL => $ctaLabel,
            LifecycleEmailTemplateDomainObjectAbstract::ENABLED_AT => $enabled ? ($existing->enabledAt ?? now()) : null,
        ];

        if ($existing->isCustomised) {
            $this->templateRepository->updateWhere($attributes, [
                LifecycleEmailTemplateDomainObjectAbstract::NUDGE_TYPE => $nudgeType->name,
            ]);
        } else {
            $this->templateRepository->create([
                LifecycleEmailTemplateDomainObjectAbstract::NUDGE_TYPE => $nudgeType->name,
                ...$attributes,
            ]);
        }

        return $this->find($nudgeType);
    }

    /**
     * @throws EmailTemplateValidationException
     */
    public function buildMail(LifecycleEmailTemplateDTO $template, LifecycleEmailRecipientDTO $recipient): LifecycleNudgeMail
    {
        $this->validate($template->subject, $template->body);

        $context = $this->contextFor($recipient);

        return new LifecycleNudgeMail(
            nudgeType: $template->nudgeType,
            renderedSubject: $this->render('subject', $template->subject, $context),
            renderedBody: $this->purifier->purify(
                $this->render('body', $template->body, $this->contextHtmlEscaper->escape($context)),
            ),
            ctaLabel: $template->ctaLabel,
            ctaUrl: $recipient->ctaUrl,
            unsubscribeUrl: $recipient->userId === null ? '#' : $this->unsubscribeTokens->pageUrlFor($recipient->userId),
            oneClickUnsubscribeUrl: $recipient->userId === null ? null : $this->unsubscribeTokens->oneClickUrlFor($recipient->userId),
            accountId: $recipient->accountId,
        );
    }

    /**
     * @throws EmailTemplateValidationException
     */
    private function render(string $field, string $template, array $context): string
    {
        try {
            return $this->liquidRenderer->render($template, $context);
        } catch (RuntimeException $e) {
            $exception = new EmailTemplateValidationException(__('The template could not be rendered'));
            $exception->validationErrors = [$field => $e->getMessage()];

            throw $exception;
        }
    }

    /**
     * @throws EmailTemplateValidationException
     */
    private function validate(string $subject, string $body): void
    {
        $errors = array_filter([
            'subject' => $this->liquidRenderer->getValidationErrors($subject),
            'body' => $this->liquidRenderer->getValidationErrors($body),
        ]);

        if ($errors !== []) {
            $exception = new EmailTemplateValidationException(__('The template contains invalid Liquid syntax'));
            $exception->validationErrors = $errors;

            throw $exception;
        }
    }

    private function contextFor(LifecycleEmailRecipientDTO $recipient): array
    {
        return [
            'first_name' => $recipient->firstName,
            ...$recipient->context,
        ];
    }

    private function toDTO(LifecycleNudgeType $nudgeType, ?LifecycleEmailTemplateDomainObject $saved): LifecycleEmailTemplateDTO
    {
        if ($saved === null) {
            return $this->nudgeRegistry->for($nudgeType)->defaultTemplate();
        }

        return new LifecycleEmailTemplateDTO(
            nudgeType: $nudgeType,
            subject: $saved->getSubject(),
            body: $saved->getBody(),
            ctaLabel: $saved->getCtaLabel(),
            enabledAt: $saved->getEnabledAt(),
            isCustomised: true,
        );
    }
}
