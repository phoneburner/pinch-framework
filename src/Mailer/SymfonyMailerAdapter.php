<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Mailer;

use PhoneBurner\Pinch\Attribute\Usage\Internal;
use PhoneBurner\Pinch\Component\EmailAddress\EmailAddress;
use PhoneBurner\Pinch\Component\Mailer\AttachmentType;
use PhoneBurner\Pinch\Component\Mailer\Mailable;
use PhoneBurner\Pinch\Component\Mailer\MailableMessage;
use PhoneBurner\Pinch\Component\Mailer\Mailer;
use PhoneBurner\Pinch\Component\Mailer\Priority;
use Symfony\Component\Mailer\MailerInterface as SymfonyMailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email as SymfonyEmail;

#[Internal]
class SymfonyMailerAdapter implements Mailer
{
    public function __construct(
        private readonly SymfonyMailerInterface $mailer,
        private readonly EmailAddress $default_from,
    ) {
    }

    #[\Override]
    public function send(Mailable $message): void
    {
        $priority = Priority::Normal;
        $from = [];
        $cc = [];
        $bcc = [];
        $reply_to = [];
        $attachments = [];

        if ($message instanceof MailableMessage) {
            $priority = $message->getPriority();
            $from = $message->getFrom();
            $cc = $message->getCc();
            $bcc = $message->getBcc();
            $reply_to = $message->getReplyTo();
            $attachments = $message->getAttachments();
        }

        $email = new SymfonyEmail();
        $email->subject($message->getSubject());
        $email->priority($priority->value);

        foreach ($message->getTo() as $email_address) {
            $email->addTo(new Address($email_address->address, $email_address->name));
        }

        foreach ($from ?: [$this->default_from] as $email_address) {
            $email->addFrom(new Address($email_address->address, $email_address->name));
        }

        foreach ($cc as $email_address) {
            $email->addCc(new Address($email_address->address, $email_address->name));
        }

        foreach ($bcc as $email_address) {
            $email->addBcc(new Address($email_address->address, $email_address->name));
        }

        foreach ($reply_to as $email_address) {
            $email->addReplyTo(new Address($email_address->address, $email_address->name));
        }

        $email->text(
            $message->getBody()?->text?->contents ?: null,
            $message->getBody()?->text?->charset ?: MailableMessage::DEFAULT_CHARSET,
        );

        $email->html(
            $message->getBody()?->html?->contents ?: null,
            $message->getBody()?->html?->charset ?: MailableMessage::DEFAULT_CHARSET,
        );

        foreach ($attachments as $attachment) {
            match ($attachment->type) {
                AttachmentType::AttachFromContent => $email->attach($attachment->content, $attachment->name, $attachment->content_type),
                AttachmentType::AttachFromPath => $email->attachFromPath($attachment->path, $attachment->name, $attachment->content_type),
                AttachmentType::EmbedFromPath => $email->embedFromPath($attachment->path, $attachment->name, $attachment->content_type),
                AttachmentType::EmbedFromContent => $email->embed($attachment->content, $attachment->name, $attachment->content_type),
            };
        }

        $this->mailer->send($email);
    }
}
