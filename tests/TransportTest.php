<?php

namespace Ohmysmtp\OhmysmtpSwiftmailer\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Ohmysmtp\OhmysmtpSwiftmailer\OhmysmtpSwiftmailerTransport;
use PHPUnit\Framework\TestCase;
use Swift_Attachment;
use Swift_Message;

require_once __DIR__ . '/../vendor/autoload.php';

class OhmysmtpTransportTest extends TestCase
{
    // Basic test that covers most features

    /** @test */
    public function sendEmailTest()
    {
        $message = (new Swift_Message());
        $message->setFrom('php@yourdomain.com', 'Your Name');
        $message->setSubject('Email Subject');

        // Addresses
        $message->addTo('someone@example.com', 'Their Name');
        $message->addTo('someone+else@example.com');
        $message->addCc('carbon_copy@example.com');
        $message->addCc('carbon_copy2@example.com', '2nd CC');
        $message->addBcc('blind_carbon_copy@example.com');
        $message->addBcc('blind_carbon_copy2@example.com', '2nd BCC');
        $message->addReplyTo('replyhere@example.com');

        // Message parts
        $message->addPart('<h1>HTML Part</h1>', 'text/html');
        $message->addPart('Text Part', 'text/plain');

        // Attachments
        $attachment = new Swift_Attachment('Plain Text Attachment', 'plain_text.txt', 'text/plain');
        $attachment2 = new Swift_Attachment('Plain Text Attachment #2', 'plain_text_2.txt', 'text/plain');
        $attachment2->setDisposition('inline');
        $message->attach($attachment);
        $message->attach($attachment2);

        // Tags via headers
        $headers = $message->getHeaders();
        $headers->addTextHeader('OMS-Tag', 'tag-1');
        $headers->addTextHeader('OMS-Tag', 'tag with spaces');

        $transport = new OhmysmtpTransportStub([new Response(200)]);
        $recipientCount = $transport->send($message);

        $this->assertEquals(6, $recipientCount);
        $transaction = $transport->getHistory()[0];
        $this->assertRequestIsAsExpected($message, $transaction['request']);
    }

    protected function assertRequestIsAsExpected($message, $request)
    {
        $attachments = $this->getAttachments($message);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('TEST_API_TOKEN', $request->getHeaderLine('OhMySMTP-Server-Token'));
        $this->assertEquals('OhMySMTP Swiftmailer Package (PHP v'.phpversion().')', $request->getHeaderLine('User-Agent'));
        $this->assertEquals('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertEquals('application/json', $request->getHeaderLine('Accept'));

        $this->assertEquals([
            'from' => 'Your Name <php@yourdomain.com>',
            'to' => 'Their Name <someone@example.com>,someone+else@example.com',
            'htmlbody' => '<h1>HTML Part</h1>',
            'textbody' => 'Text Part',
            'cc' => 'carbon_copy@example.com,2nd CC <carbon_copy2@example.com>',
            'bcc' => 'blind_carbon_copy@example.com,2nd BCC <blind_carbon_copy2@example.com>',
            'subject' => 'Email Subject',
            'replyto' => 'replyhere@example.com',
            'tags' => ['tag-1', 'tag with spaces'],
            'attachments' => [
                [
                    'content_type' => 'text/plain',
                    'content' => 'UGxhaW4gVGV4dCBBdHRhY2htZW50',
                    'name' => 'plain_text.txt',
                ],
                [
                    'content_type' => 'text/plain',
                    'content' => 'UGxhaW4gVGV4dCBBdHRhY2htZW50ICMy',
                    'name' => 'plain_text_2.txt',
                    'cid' => 'cid:'.$attachments[1]->getId(),
                ],
            ],
        ], json_decode($request->getBody()->getContents(), true));
    }

    protected function getAttachments($message)
    {
        return array_values(array_filter($message->getChildren(), function ($child) {
            return $child instanceof Swift_Attachment;
        }));
    }

    public function testUnauthorizedRequest()
    {
        $message = (new Swift_Message());
        $transport = new OhmysmtpTransportStub([new Response(403)]);
        $result = $transport->send($message);
        $this->assertEquals($result, 0);
    }
}

class OhmysmtpTransportStub extends OhmysmtpSwiftmailerTransport
{
    protected $client;

    public function __construct(array $responses = [])
    {
        parent::__construct('TEST_API_TOKEN');

        $this->client = $this->mockGuzzle($responses);
    }

    protected function getHttpClient()
    {
        return $this->client;
    }

    public function getHistory()
    {
        return $this->client->transactionHistory;
    }

    private function mockGuzzle(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $client = new Client(['handler' => $stack]);
        $client->transactionHistory = [];
        $stack->push(Middleware::history($client->transactionHistory));

        return $client;
    }
}
