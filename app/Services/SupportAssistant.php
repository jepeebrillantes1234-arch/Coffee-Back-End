<?php

namespace App\Services;

class SupportAssistant
{
    /**
     * First-line, local automated help. It does not send customer messages to a third party.
     */
    public function reply(string $message): string
    {
        $text = mb_strtolower($message);

        if (preg_match('/forgot|forget|remember.*email|email.*forgot|password|sign.?in|login|nakalimutan/', $text)) {
            return 'I can help you get this to our admin. Never share your password or verification code in chat. If you forgot your email too, send a phone number or another way to reach you and, if available, an order number. Our admin will verify your identity before helping with account access.';
        }

        if (preg_match('/refund|money back|return.*payment|ibalik.*bayad/', $text)) {
            return 'I’m sorry you need help with a payment. Please select the related order or include its order number and briefly explain the issue. An admin will review the payment and reply here; refunds are not automatic.';
        }

        if (preg_match('/wrong|damaged|spoiled|missing|bad|not.*right|product|item|order.*issue/', $text)) {
            return 'Sorry about the problem with your order. Please include the order number and attach a clear photo or a short video of the item. An admin will review it and reply here.';
        }

        if (preg_match('/where.*order|track|delivery|rider|late|status/', $text)) {
            return 'You can see live order progress from My Orders. If you still need help, send the order number here and an admin will check it.';
        }

        return 'Thanks for contacting KJM Coffee Shop. I’ve received your message. An admin will review the details and reply here. Please do not send passwords, verification codes, or full payment card details.';
    }
}