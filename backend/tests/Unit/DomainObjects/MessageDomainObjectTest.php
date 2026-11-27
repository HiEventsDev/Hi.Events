<?php

namespace Tests\Unit\DomainObjects;

use HiEvents\DomainObjects\MessageDomainObject;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MessageDomainObjectTest extends TestCase
{
    public static function sendData(): array
    {
        return [
            'test send' => [['is_test' => true], true],
            'test send stored as json' => ['{"is_test": true, "account_id": 1}', true],
            'real send' => [['is_test' => false], false],
            'no flag' => [['account_id' => 1], false],
            'truthy non-boolean' => [['is_test' => 'true'], false],
            'no send data' => [null, false],
        ];
    }

    #[DataProvider('sendData')]
    public function test_a_message_is_a_test_only_when_its_send_data_says_so(array|string|null $sendData, bool $expected): void
    {
        $message = (new MessageDomainObject)->setSendData($sendData);

        $this->assertSame($expected, $message->isTest());
    }
}
