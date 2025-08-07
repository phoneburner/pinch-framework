<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\EventSourcing\MessageReplay;

use Doctrine\ORM\EntityManagerInterface;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageRepository;
use EventSauce\EventSourcing\PaginationCursor;
use EventSauce\EventSourcing\ReplayingMessages\ReplayResult;
use EventSauce\EventSourcing\ReplayingMessages\TriggerAfterReplay;
use EventSauce\EventSourcing\ReplayingMessages\TriggerBeforeReplay;

class ReplayMessagesAndFlushEntityManager
{
    /**
     * @param array<MessageConsumer> $consumers
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $repository,
        private readonly array $consumers,
    ) {
    }

    public function replayBatch(PaginationCursor $cursor): ReplayResult
    {
        if ($cursor->isAtStart()) {
            foreach ($this->consumers as $consumer) {
                if ($consumer instanceof TriggerBeforeReplay) {
                    $consumer->beforeReplay();
                }
            }
        }

        $messages_handled = 0;
        $messages = $this->repository->paginate($cursor);

        foreach ($messages as $message) {
            \assert($message instanceof Message);
            foreach ($this->consumers as $consumer) {
                $consumer->handle($message);
            }

            $this->em->flush(); // Flush after each message to ensure changes are persisted
            $this->em->clear(); // Clear the EntityManager to avoid memory issues
            ++$messages_handled;
        }

        if ($messages_handled === 0) {
            foreach ($this->consumers as $consumer) {
                if ($consumer instanceof TriggerAfterReplay) {
                    $consumer->afterReplay();
                }
            }
        }

        return new ReplayResult($messages_handled, $messages->getReturn());
    }
}
