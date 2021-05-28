<?php

namespace Ohmysmtp\OhmysmtpSwiftmailer;

use GuzzleHttp\Client;
use Swift_Events_EventListener;
use Swift_Mime_MimePart;
use Swift_Mime_SimpleMessage;
use Swift_Transport;

// Implements 'isStarted', 'start', 'stop', 'ping', 'send', 'registerPlugin'
class OhmysmtpSwiftmailerTransport implements Swift_Transport
{
    protected $version = '';
    protected $os = '';

    /**
     * The OhMySMTP API Token
     *
     * @var string
     */
    protected $apiToken;

    /**
     * @var \Swift_Events_EventDispatcher
     */
    protected $_eventDispatcher;

    /**
     * Create a new transport.
     *
     * @param  string  $apiToken The API token for your OhMySMTP organization
     * @return void
     */
    public function __construct($apiToken)
    {
        $this->version = phpversion();
        $this->os = PHP_OS;
        $this->_eventDispatcher = \Swift_DependencyContainer::getInstance()->lookup('transport.eventdispatcher');
        $this->apiToken = $apiToken;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $client = $this->getHttpClient();

        if ($sendEvent = $this->_eventDispatcher->createSendEvent($this, $message)) {
            $this->_eventDispatcher->dispatchEvent($sendEvent, 'beforeSendPerformed');
            if ($sendEvent->bubbleCancelled()) {
                return 0;
            }
        }

        $php_version = $this->version;

        $response = $client->request('POST', 'https://app.ohmysmtp.com/api/v1/send', [
            'json' => $this->convertToOms($message),
            'headers' => [
                'OhMySMTP-Server-Token' => $this->apiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => "OhMySMTP Swiftmailer Package (PHP v$php_version)",
            ],
            'http_errors' => false, // Errors are handled by Swiftmailer event listener
        ]);

        $success = $response->getStatusCode() === 200;

        if ($responseEvent = $this->_eventDispatcher->createResponseEvent($this, $response->getBody()->__toString(), $success)) {
            $this->_eventDispatcher->dispatchEvent($responseEvent, 'responseReceived');
        }

        $sendEvent->setResult($success ? \Swift_Events_SendEvent::RESULT_SUCCESS : \Swift_Events_SendEvent::RESULT_FAILED);
        $this->_eventDispatcher->dispatchEvent($sendEvent, 'sendPerformed');

        return $success ? $this->countSent($message) : 0;
    }

    /**
     * Get the number of delivered addresses
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return int
     */
    protected function countSent(Swift_Mime_SimpleMessage $message)
    {
        return count(
            array_merge(
                (array) $message->getTo(),
                (array) $message->getCc(),
                (array) $message->getBcc()
            )
        );
    }

    /**
     * Prep email by converting from emails from dict to array
     *
     * @param array $emails
     * @return array
     */
    protected function prepareEmailAddresses(array|string $emails)
    {
        $convertedEmails = [];
        foreach ($emails as $email => $name) {
            $convertedEmails[] = $name ? $name . " <{$email}>" : $email;
        }

        return $convertedEmails;
    }

    /**
     * Extract the MIME type requested
     *
     * @param Swift_Mime_SimpleMessage $message
     * @param string $mimeType
     * @return Swift_Mime_MimePart|null
     */
    protected function getMIMEPart(Swift_Mime_SimpleMessage $message, $mimeType)
    {
        foreach ($message->getChildren() as $part) {
            if (strpos($part->getContentType(), $mimeType) === 0 && ! ($part instanceof \Swift_Mime_Attachment)) {
                return $part;
            }
        }
    }

    /**
     * Convert a Swift MIME Message to an OhMySMTP API object
     * See https://docs.ohmysmtp.com/reference/send for details
     *
     * @param  Swift_Mime_SimpleMessage  $message
     * @return array<array-key, mixed>
     */
    protected function convertToOms(Swift_Mime_SimpleMessage $message)
    {
        $payload = [];

        $this->addAddresses($payload, $message);
        $this->addSubject($payload, $message);
        $this->processMessageParts($payload, $message);
        if ($message->getHeaders()) {
            $this->processTagsFromHeaders($payload, $message);
        }

        return $payload;
    }

    /**
     * Add SwiftMailer recipients to OMS payload
     *
     * @param  array                     $payload
     * @param  Swift_Mime_SimpleMessage  $message
     */
    protected function addAddresses(&$payload, $message)
    {
        $payload['from'] = join(',', $this->prepareEmailAddresses($message->getFrom()));
        if ($to = $message->getTo()) {
            $payload['to'] = join(',', $this->prepareEmailAddresses($to));
        }
        if ($cc = $message->getCc()) {
            $payload['cc'] = join(',', $this->prepareEmailAddresses($cc));
        }
        if ($bcc = $message->getBcc()) {
            $payload['bcc'] = join(',', $this->prepareEmailAddresses($bcc));
        }
        if ($reply_to = $message->getReplyTo()) {
            /**
             * @psalm-suppress InvalidArgument
            */
            $payload['replyto'] = join(',', $this->prepareEmailAddresses($reply_to));
        }
    }

    /**
     * Add swiftmailer subject to OMS payload
     *
     * @param  array                     $payload
     * @param  Swift_Mime_SimpleMessage  $message
     */
    protected function addSubject(&$payload, $message)
    {
        $payload['subject'] = $message->getSubject();
    }

    /**
     * Turn SwiftMailer MIME parts into htmlbody, textbody and attachment array
     *
     * @param  array                     $payload
     * @param  Swift_Mime_SimpleMessage  $message
     */
    protected function processMessageParts(&$payload, $message)
    {
        switch ($message->getContentType()) {
            case 'text/html':
            case 'multipart/alternative':
            case 'multipart/mixed':
                $payload['htmlbody'] = $message->getBody();

                break;
            default:
                $payload['textbody'] = $message->getBody();

                break;
        }

        // If there are other html or text parts include them
        if ($plain = $this->getMIMEPart($message, 'text/plain')) {
            $payload['textbody'] = $plain->getBody();
        }
        if ($html = $this->getMIMEPart($message, 'text/html')) {
            $payload['htmlbody'] = $html->getBody();
        }

        // Attachments
        if ($message->getChildren()) {
            $payload['attachments'] = [];
            foreach ($message->getChildren() as $attachment) {
                if (is_object($attachment) and $attachment instanceof \Swift_Mime_Attachment) {
                    $attachments = [
                        'name' => $attachment->getFilename(),
                        'content' => base64_encode($attachment->getBody()),
                        'content_type' => $attachment->getContentType(),
                    ];
                    if ($attachment->getDisposition() != 'attachment' && $attachment->getId() != null) {
                        $attachments['cid'] = 'cid:' . $attachment->getId();
                    }
                    $payload['attachments'][] = $attachments;
                }
            }
        }
    }

    /**
     * Move OMS-Tags from headers into the API payload
     *
     * @param  array                     $payload
     * @param  Swift_Mime_SimpleMessage  $message
     */
    protected function processTagsFromHeaders(&$payload, $message)
    {
        $payload['tags'] = [];
        foreach ($message->getHeaders()->getAll() as $value) {
            $fieldName = $value->getFieldName();
            if ($fieldName == 'OMS-Tag') {
                array_push($payload['tags'], $value->getValue());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->_eventDispatcher->bindEventListener($plugin);
    }

    /**
     * Get Guzzle HTTP client instance
     *
     * @return \GuzzleHttp\Client
     */
    protected function getHttpClient()
    {
        return new Client;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        return true;
    }

    /**
     * Ping
     *
     * @return bool
     */
    public function ping()
    {
        return true;
    }
}
