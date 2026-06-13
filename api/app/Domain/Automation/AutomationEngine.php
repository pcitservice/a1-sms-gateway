<?php

namespace App\Domain\Automation;

use App\Domain\Sms\Services\SmsDispatcher;
use App\Domain\Webhooks\WebhookDispatcher;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\SmsMessage;
use GuzzleHttp\Client;

/**
 * Trigger/action engine.
 *
 * Triggers:
 *   - incoming_sms  → always fires on any inbound message
 *   - keyword       → trigger_config.keyword (case-insensitive prefix match)
 *   - delivery      → fires when outbound message hits 'delivered'
 *   - failed        → fires when outbound message hits 'failed'
 *
 * Actions:
 *   - send_reply  → enqueue an outbound reply to the sender
 *   - webhook     → POST event payload to an arbitrary URL
 *   - api_call    → arbitrary HTTP call with templated body
 *   - tag_contact → attach a tag to the contact
 */
class AutomationEngine
{
    public function __construct(
        protected SmsDispatcher    $sms,
        protected WebhookDispatcher $webhooks,
        protected Client            $http,
    ) {}

    public function runForIncoming(SmsMessage $message): void
    {
        if (! $message->team_id) {
            return;
        }
        $automations = Automation::query()
            ->where('team_id', $message->team_id)
            ->where('is_active', true)
            ->whereIn('trigger_type', ['incoming_sms', 'keyword'])
            ->get();

        foreach ($automations as $auto) {
            if ($auto->trigger_type === 'keyword' && ! $this->matchesKeyword($auto, $message->body)) {
                continue;
            }
            $this->execute($auto, $message);
        }
    }

    public function runForDelivery(SmsMessage $message, bool $delivered): void
    {
        $type = $delivered ? 'delivery' : 'failed';
        $autos = Automation::query()
            ->where('team_id', $message->team_id)
            ->where('is_active', true)
            ->where('trigger_type', $type)
            ->get();

        foreach ($autos as $a) {
            $this->execute($a, $message);
        }
    }

    private function matchesKeyword(Automation $a, string $body): bool
    {
        $kw = strtoupper(trim($a->trigger_config['keyword'] ?? ''));
        if ($kw === '') {
            return false;
        }
        $firstWord = strtoupper(strtok(trim($body), " \n\t"));
        return $firstWord === $kw;
    }

    private function execute(Automation $a, SmsMessage $msg): void
    {
        $result = [];
        try {
            foreach ($a->actions as $action) {
                $result[] = $this->runAction($action, $a, $msg);
            }
            AutomationRun::create([
                'automation_id' => $a->id,
                'message_id'    => $msg->id,
                'status'        => 'success',
                'result'        => $result,
            ]);
            $a->forceFill(['last_run_at' => now()])->increment('execution_count');
        } catch (\Throwable $e) {
            AutomationRun::create([
                'automation_id' => $a->id,
                'message_id'    => $msg->id,
                'status'        => 'failed',
                'result'        => ['error' => $e->getMessage(), 'partial' => $result],
            ]);
        }
    }

    private function runAction(array $action, Automation $a, SmsMessage $msg): array
    {
        return match ($action['type'] ?? '') {
            'send_reply' => $this->actSendReply($action, $a, $msg),
            'webhook'    => $this->actWebhook($action, $msg),
            'api_call'   => $this->actApiCall($action, $msg),
            'tag_contact'=> $this->actTagContact($action, $msg),
            default      => ['skipped' => 'unknown action type'],
        };
    }

    private function actSendReply(array $a, Automation $auto, SmsMessage $msg): array
    {
        $body = $this->template($a['body'] ?? '', $msg);
        app()->instance('current_team', $msg->team);
        $reply = $this->sms->dispatch($msg->team, ['to' => $msg->from, 'message' => $body]);
        return ['reply_id' => $reply->id];
    }

    private function actWebhook(array $a, SmsMessage $msg): array
    {
        $this->webhooks->dispatch($msg->team_id, $a['event'] ?? 'automation.fired', [
            'automation_action' => $a,
            'message'           => $msg->only('id', 'from', 'to', 'body'),
        ]);
        return ['queued' => true];
    }

    private function actApiCall(array $a, SmsMessage $msg): array
    {
        $resp = $this->http->request($a['method'] ?? 'POST', $a['url'], [
            'json'    => $this->templateArray($a['payload'] ?? [], $msg),
            'headers' => $a['headers'] ?? [],
            'timeout' => 10,
            'http_errors' => false,
        ]);
        return ['status' => $resp->getStatusCode()];
    }

    private function actTagContact(array $a, SmsMessage $msg): array
    {
        $contact = \App\Models\Contact::query()
            ->where('team_id', $msg->team_id)->where('msisdn', $msg->from)->first();
        if (! $contact) return ['skipped' => 'no contact'];
        $tag = \App\Models\ContactTag::firstOrCreate(['team_id' => $msg->team_id, 'name' => $a['tag']]);
        $contact->tags()->syncWithoutDetaching([$tag->id]);
        return ['tagged' => $tag->name];
    }

    private function template(string $tpl, SmsMessage $msg): string
    {
        $vars = [
            'from'     => $msg->from,
            'to'       => $msg->to,
            'body'     => $msg->body,
            'message'  => $msg->body,
        ];
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{{'.$k.'}}', (string) $v, $tpl);
        }
        return $tpl;
    }

    private function templateArray(array $payload, SmsMessage $msg): array
    {
        array_walk_recursive($payload, function (&$v) use ($msg) {
            if (is_string($v)) $v = $this->template($v, $msg);
        });
        return $payload;
    }
}
