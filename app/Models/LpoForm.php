<?php

declare(strict_types=1);

namespace Models;

use Support\FormPdf;
use Support\Report;

/**
 * The LPO form sent to a customer once they accept a price offer.
 *
 * A Local Purchase Order is the customer's own document: their official order,
 * with their number, signed and stamped, that their accounts department will
 * match our invoice against. Many customers have no template of their own, or
 * fill theirs in wrongly, so the company sends one ready to complete: our
 * details, the agreed routes and prices and the terms are printed; everything
 * that is the customer's to say is a box they type into, then they sign, stamp
 * and send it back.
 */
final class LpoForm
{
    private const L = 36.0;
    private const R = 559.0;
    private const BOTTOM = 784.0;

    private const INK = [0.12, 0.15, 0.19];
    private const GREY = [0.42, 0.47, 0.53];
    private const RULE = [0.80, 0.83, 0.87];

    public static function filename(array $offer): string
    {
        return 'LPO-form-' . preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $offer['offer_code']) . '.pdf';
    }

    /** The form as a file under the uploads folder, written fresh each time, ready to attach. */
    public static function store(int $offerId): ?array
    {
        $built = self::build($offerId);
        if ($built === null) {
            return null;
        }
        $directory = \Support\Mailer::uploadsRoot() . '/price_offers/lpo-forms';
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return null;
        }
        $path = $directory . '/' . $built['filename'];
        if (@file_put_contents($path, $built['bytes']) === false) {
            return null;
        }

        return ['filename' => $built['filename'], 'path' => $path];
    }

    /** @return array{filename: string, bytes: string}|null */
    public static function build(int $offerId): ?array
    {
        $offer = PriceOffer::offer($offerId);
        if ($offer === null) {
            return null;
        }
        $cards = PriceOffer::cards($offerId);

        $brand = Report::brand();
        $accent = $brand['pdf'];
        $tint = [hexdec(substr($brand['tint'], 0, 2)) / 255, hexdec(substr($brand['tint'], 2, 2)) / 255, hexdec(substr($brand['tint'], 4, 2)) / 255];
        $company = \company_name();
        $code = (string) $offer['offer_code'];
        $returnTo = trim((string) ($offer['sender_email'] ?? '')) ?: trim(Settings::get('company_email', ''));
        $validUntil = $offer['valid_until'] ? date('j F Y', (int) strtotime((string) $offer['valid_until'])) : '';
        $terms = (int) ($offer['payment_terms_days'] ?: Settings::int('payment_terms_days', 30));

        $pdf = new FormPdf('Local Purchase Order - ' . $company . ' - ' . $code);
        $L = self::L;
        $width = self::R - self::L;

        // ------------------------------------------------------------ header
        $logoWidth = $brand['logo'] ? $pdf->logo((string) $brand['logo'], $L, 34, 110, 44) : 0.0;
        $textX = $L + ($logoWidth > 0 ? $logoWidth + 10 : 0);
        $pdf->text($textX, 36, $company, 14, true, self::INK);
        $top = 56.0;
        $details = array_filter([
            trim(Settings::get('company_address', '')),
            implode('  ·  ', array_filter([trim(Settings::get('company_phone', '')), trim(Settings::get('company_email', ''))])),
            ($tin = trim(Settings::get('company_tin', ''))) !== '' ? 'TIN ' . $tin : '',
        ]);
        foreach ($details as $detail) {
            $pdf->text($textX, $top, $detail, 8, false, self::GREY);
            $top += 11;
        }

        $pdf->text($L, 34, 'LOCAL PURCHASE ORDER', 16, true, $accent, 'right', $width);
        $pdf->text($L, 55, 'Order for transport services', 8.5, false, self::GREY, 'right', $width);

        // The order's numbers: theirs to fill, ours printed.
        $boxX = 355.0;
        $boxW = self::R - $boxX;
        $rowTop = 74.0;
        $refRows = [
            ['LPO number', 'field', 'lpo_number', ''],
            ['LPO date', 'field', 'lpo_date', ''],
            ['Our offer reference', 'text', '', $code],
            ['Offer valid until', 'text', '', $validUntil !== '' ? $validUntil : '-'],
        ];
        $pdf->rect($boxX, $rowTop, $boxW, count($refRows) * 19 + 6, null, self::RULE);
        foreach ($refRows as $i => [$label, $kind, $name, $value]) {
            $y = $rowTop + 4 + $i * 19;
            $pdf->text($boxX + 7, $y + 4, $label, 7.8, $i < 2, $i < 2 ? self::INK : self::GREY);
            if ($kind === 'field') {
                $pdf->field($name, $boxX + 92, $y, $boxW - 99, 15, $value, false, 9);
            } else {
                $pdf->text($boxX + 94, $y + 4, $value, 8.5, true, self::INK);
            }
        }

        // How to fill it in, beside the numbers.
        $noteTop = max($top + 6, 82.0);
        $note = sprintf(
            'Prepared by %s from offer %s. Our details, the agreed routes and prices and the terms are already filled in. '
            . 'Please complete the shaded boxes (type in them, or print and write), sign, stamp and return the form%s.',
            $company, $code, $returnTo !== '' ? ' to ' . $returnTo : ''
        );
        $lines = FormPdf::wrap($note, 296, 7.8);
        $pdf->rect($L, $noteTop, 308, count($lines) * 10.5 + 12, $tint);
        $pdf->paragraph($L + 7, $noteTop + 6, 296, $note, 7.8, false, self::INK, 1.35);

        $y = max($rowTop + count($refRows) * 19 + 6, $noteTop + count($lines) * 10.5 + 12) + 10;
        $pdf->line($L, $y, self::R, $y, 1.4, $accent);
        $y += 10;

        $section = static function (string $title, float $top, ?string $aside = null) use ($pdf, $L, $width, $tint, $accent): float {
            $pdf->rect($L, $top, $width, 16, $tint);
            $pdf->text($L + 7, $top + 4.5, strtoupper($title), 8, true, $accent);
            if ($aside !== null) {
                $pdf->text($L, $top + 5, $aside, 7, false, self::GREY, 'right', $width - 7);
            }

            return $top + 22;
        };

        // ----------------------------------------------------------- parties
        $half = ($width - 14) / 2;
        $rightX = $L + $half + 14;
        $pdf->text($L, $y, 'SUPPLIER', 8, true, $accent);
        $pdf->text($L, $y, 'filled in by us', 7, false, self::GREY, 'right', $half);
        $pdf->text($rightX, $y, 'CUSTOMER (BUYER)', 8, true, $accent);
        $pdf->text($rightX, $y, 'please check and complete', 7, false, self::GREY, 'right', $half);
        $y += 13;
        $pdf->line($L, $y, $L + $half, $y, 0.6, self::RULE);
        $pdf->line($rightX, $y, self::R, $y, 0.6, self::RULE);
        $y += 5;

        $supplier = array_filter([
            'Company' => $company,
            'Address' => trim(Settings::get('company_address', '')),
            'Phone' => trim(Settings::get('company_phone', '')),
            'Email' => trim(Settings::get('company_email', '')),
            'TIN' => trim(Settings::get('company_tin', '')),
            'Contact person' => trim((string) ($offer['sender_name'] ?? '')) . (trim((string) ($offer['sender_title'] ?? '')) !== '' ? ', ' . trim((string) $offer['sender_title']) : ''),
            'Contact details' => implode('  ·  ', array_filter([trim((string) ($offer['sender_phone'] ?? '')), trim((string) ($offer['sender_email'] ?? ''))])),
        ], static fn (string $v): bool => trim($v, ' ,') !== '');
        $buyer = [
            ['Company name', 'buyer_company', (string) $offer['customer_name']],
            ['TIN', 'buyer_tin', (string) ($offer['customer_tin'] ?? '')],
            ['Address', 'buyer_address', (string) ($offer['customer_address'] ?? '')],
            ['City and country', 'buyer_city', ''],
            ['Phone', 'buyer_phone', (string) ($offer['customer_phone'] ?? '')],
            ['Email', 'buyer_email', (string) ($offer['email'] ?? '')],
            ['Contact person', 'buyer_contact', (string) ($offer['contact_name'] ?? '')],
        ];

        $row = 18.0;
        $i = 0;
        foreach ($supplier as $label => $value) {
            $pdf->text($L, $y + $i * $row + 4, $label, 7.6, false, self::GREY);
            $pdf->text($L + 80, $y + $i * $row + 3.5, self::cut($value, $half - 80, 8.5), 8.5, $label === 'Company', self::INK);
            $i++;
        }
        foreach ($buyer as $j => [$label, $name, $value]) {
            $pdf->text($rightX, $y + $j * $row + 4, $label, 7.6, false, self::GREY);
            $pdf->field($name, $rightX + 80, $y + $j * $row, $half - 80, 15, $value);
        }
        $y += max(count($supplier), count($buyer)) * $row + 10;

        // --------------------------------------------------------- services
        $y = $section('Services ordered', $y, 'Unit prices as agreed in offer ' . $code);
        $cols = [
            ['#', 18.0, 'left'],
            ['Route', 150.0, 'left'],
            ['Service', 72.0, 'left'],
            ['Unit price (agreed)', 103.0, 'left'],
            ['Quantity', 60.0, 'left'],
            ['Amount', $width - 403.0, 'left'],
        ];
        $x = $L;
        foreach ($cols as [$label, $w]) {
            $pdf->text($x + 3, $y, strtoupper($label), 7, true, self::GREY);
            $x += $w;
        }
        $y += 12;
        $pdf->line($L, $y, self::R, $y, 0.8, self::RULE);

        $currencies = [];
        $hasShared = false;
        foreach ($cards as $n => $card) {
            $currency = (string) ($card['currency'] ?: Currency::base());
            $currencies[$currency] = true;
            $shared = ($card['service_type'] ?? '') === 'shared';
            $hasShared = $hasShared || $shared;
            $route = (trim((string) ($card['from_depot'] ?? '')) ?: (string) $card['origin']) . ' to ' . (trim((string) ($card['to_depot'] ?? '')) ?: (string) $card['destination']);
            if (trim((string) ($card['note'] ?? '')) !== '') {
                $route .= ' (' . trim((string) $card['note']) . ')';
            }
            $service = PriceOffer::service((string) ($card['service_type'] ?? ''));
            $maxKg = (float) ($card['full_load_kg'] ?? 0);
            if (!$shared && $maxKg > 0) {
                $service .= ', up to ' . number_format($maxKg) . ' kg';
            }
            $price = Currency::format((float) $card['rate_amount'], $currency) . ' ' . PriceOffer::basis((string) $card['rate_type']);
            $minimum = (float) $card['minimum_charge'] > 0 && !in_array($card['rate_type'], ['per_trip', 'per_day'], true)
                ? 'minimum ' . Currency::format((float) $card['minimum_charge'], $currency) : '';

            $routeLines = FormPdf::wrap($route, 144, 8);
            $serviceLines = FormPdf::wrap($service, 68, 8);
            $priceLines = FormPdf::wrap($price, 99, 8);
            $height = max(26.0, max(count($routeLines), count($serviceLines), count($priceLines) + ($minimum !== '' ? 1 : 0)) * 10.5 + 10);
            if ($y + $height > self::BOTTOM) {
                $y = self::continued($pdf, $company, $accent);
            }

            $top = $y + 6;
            $pdf->text($L + 3, $top, (string) ($n + 1), 8, false, self::INK);
            foreach ($routeLines as $k => $line) {
                $pdf->text($L + 21, $top + $k * 10.5, $line, 8, $k === 0, self::INK);
            }
            foreach ($serviceLines as $k => $line) {
                $pdf->text($L + 171, $top + $k * 10.5, $line, 8, false, self::INK);
            }
            foreach ($priceLines as $k => $line) {
                $pdf->text($L + 243, $top + $k * 10.5, $line, 8, true, self::INK);
            }
            if ($minimum !== '') {
                $pdf->text($L + 243, $top + count($priceLines) * 10.5, $minimum, 7.2, false, self::GREY);
            }
            $pdf->field('quantity_' . ($n + 1), $L + 346, $y + 5, 54, 15);
            $pdf->field('amount_' . ($n + 1), $L + 406, $y + 5, $width - 406, 15);
            $y += $height;
            $pdf->line($L, $y, self::R, $y, 0.5, self::RULE);
        }

        // Totals on the right, the amount in words on the left.
        $suffix = count($currencies) === 1 ? ' (' . array_key_first($currencies) . ')' : '';
        $y += 7;
        $totalsTop = $y;
        foreach ([['Sub-total' . $suffix, 'subtotal', false], ['VAT' . $suffix, 'vat', false], ['Total' . $suffix, 'total', true]] as $k => [$label, $name, $strong]) {
            $pdf->text($L + 250, $totalsTop + $k * 19 + 4, $label, 8, $strong, self::INK, 'right', 150);
            $pdf->field($name, $L + 406, $totalsTop + $k * 19, $width - 406, 15, '', false, $strong ? 9.5 : 8.5);
        }
        $quantityNote = 'Quantity: the number of trips for a full truck' . ($hasShared ? ', or the weight in kg for a shared truck (the minimum charge applies)' : '') . '.';
        $pdf->paragraph($L, $totalsTop + 1, 230, $quantityNote, 7.2, false, self::GREY);
        $pdf->text($L, $totalsTop + 22, 'Total amount in words', 7.6, false, self::GREY);
        $pdf->field('amount_in_words', $L, $totalsTop + 32, 300, 22, '', true, 8);
        $y = $totalsTop + 3 * 19 + 8;

        // A grid of boxes, label above each: [label, name, value, full width?]
        $grid = static function (array $cells, float $top) use ($pdf, $L, $width): float {
            $half = ($width - 14) / 2;
            $column = 0;
            foreach ($cells as [$label, $name, $value, $full]) {
                if ($full && $column === 1) {
                    $top += 32;
                    $column = 0;
                }
                $x = $column === 0 ? $L : $L + $half + 14;
                $w = $full ? $width : $half;
                $pdf->text($x, $top, $label, 7.6, false, self::GREY);
                if ($name === null) {
                    $pdf->text($x, $top + 13, $value, 8.5, true, self::INK);
                } else {
                    $pdf->field($name, $x, $top + 10, $w, 16, $value);
                }
                if ($full || $column === 1) {
                    $top += 32;
                    $column = 0;
                } else {
                    $column = 1;
                }
            }

            return $top + ($column === 1 ? 32 : 0);
        };

        // ------------------------------------------------- goods and collection
        $first = $cards[0] ?? [];
        $goods = [
            ['Description of the goods', 'goods_description', '', true],
            ['Number of packages', 'packages', '', false],
            ['Gross weight (kg)', 'gross_weight_kg', '', false],
            ['Container number(s) and size', 'containers', '', false],
            ['Bill of lading / AWB number', 'bill_of_lading', '', false],
            ['Collection address', 'collection_address', (string) (($first['from_depot'] ?? '') ?: ($first['origin'] ?? '')), false],
            ['Requested collection date', 'collection_date', '', false],
            ['Delivery address', 'delivery_address', (string) (($first['to_depot'] ?? '') ?: ($first['destination'] ?? '')), false],
            ['Receiver at delivery (name and phone)', 'consignee', '', false],
            ['Clearing agent (name and phone)', 'clearing_agent', '', false],
            ['Special handling (fragile, hazardous, temperature)', 'special_handling', '', false],
        ];
        if ($y + 22 + 6 * 32 > self::BOTTOM) {
            $y = self::continued($pdf, $company, $accent);
        }
        $y = $section('Goods and collection', $y + 4, 'to be completed by the customer');
        $y = $grid($goods, $y);

        // ---------------------------------------------------- invoicing
        $invoicing = [
            ['Invoice to be addressed to (if different from above)', 'invoice_to', '', false],
            ['Email for invoices (accounts)', 'invoice_email', '', false],
            ['Your internal reference or cost centre', 'cost_centre', '', false],
            ['Payment terms', null, sprintf('%d days from the date of invoice', $terms), false],
        ];
        if ($y + 22 + 2 * 32 > self::BOTTOM) {
            $y = self::continued($pdf, $company, $accent);
        }
        $y = $section('Invoicing and payment', $y + 4);
        $y = $grid($invoicing, $y);

        // ------------------------------------------------------------ terms
        $termLines = [
            sprintf('Prices are those agreed in our offer %s%s, and are charged in the currency shown against each service.', $code, $validUntil !== '' ? ', valid until ' . $validUntil : ''),
            'Included in the price: carriage from the point of collection to the destination shown, the truck and its driver, fuel, road tolls and tracking of the goods throughout the journey.',
            'Not included: customs duty and taxes, port and storage charges and other government fees. Where we pay these on the customer\'s behalf, they are recharged at cost on the invoice, with the receipts.',
        ];
        if ($hasShared) {
            $termLines[] = 'Shared truck services are charged on the actual weight loaded, subject to the minimum charge shown.';
        }
        $termLines[] = 'Container detention charged by the shipping line beyond its free days is recharged at cost.';
        $termLines[] = sprintf('Our invoice will quote this LPO number. Payment is due within %d days from the date of invoice.', $terms);
        $termLines[] = 'This order is valid only when signed and stamped by the customer\'s authorised signatories.';

        $termsHeight = 22.0;
        foreach ($termLines as $line) {
            $termsHeight += count(FormPdf::wrap($line, $width - 14, 7.8)) * 10.5 + 3;
        }
        if ($y + $termsHeight + 150 > self::BOTTOM) {
            $y = self::continued($pdf, $company, $accent);
        }
        $y = $section('Terms of this order', $y + 4);
        foreach ($termLines as $n => $line) {
            $pdf->text($L, $y, ($n + 1) . '.', 7.8, true, self::GREY);
            $y += $pdf->paragraph($L + 14, $y, $width - 14, $line, 7.8, false, self::INK) + 3;
        }

        // ---------------------------------------------------- authorisation
        $y = $section('Authorised by the customer', $y + 8);
        $columnW = 186.0;
        $stampX = $L + 2 * ($columnW + 12);
        foreach (['Prepared by' => 'prepared', 'Approved by' => 'approved'] as $title => $key) {
            $x = $key === 'prepared' ? $L : $L + $columnW + 12;
            $pdf->text($x, $y, $title, 8.2, true, self::INK);
            $top = $y + 13;
            foreach (['Name' => 'name', 'Position' => 'position', 'Date' => 'date'] as $label => $suffixName) {
                $pdf->text($x, $top + 4, $label, 7.6, false, self::GREY);
                $pdf->field($key . '_' . $suffixName, $x + 46, $top, $columnW - 46, 15);
                $top += 19;
            }
            $pdf->text($x, $top + 4, 'Signature', 7.6, false, self::GREY);
            $pdf->line($x + 46, $top + 30, $x + $columnW, $top + 30, 0.7, self::GREY);
        }
        $stampW = self::R - $stampX;
        $pdf->rect($stampX, $y, $stampW, 104, null, self::GREY, 0.6);
        $pdf->text($stampX, $y + 46, 'Official company stamp', 7.6, false, self::GREY, 'center', $stampW);
        $y += 120;

        // How to send it back, where nobody can miss it.
        $steps = [
            'Type in the shaded boxes on a computer or phone (Adobe Reader, Chrome or Edge) and save the file, or print the form and write in it.',
            'Sign and stamp it: on paper, or with "Fill & Sign" in Adobe Reader.',
            sprintf('Send it back%s, quoting %s. A scan or a photo of the signed page is enough.', $returnTo !== '' ? ' to ' . $returnTo : ' by reply to our email', $code),
            'Once we receive it, we confirm the collection date, the truck and the driver.',
        ];
        $stepsHeight = 26.0;
        foreach ($steps as $step) {
            $stepsHeight += count(FormPdf::wrap($step, $width - 34, 7.8)) * 10.5 + 3;
        }
        if ($y + $stepsHeight > self::BOTTOM) {
            $y = self::continued($pdf, $company, $accent);
        }
        $pdf->rect($L, $y, $width, $stepsHeight, $tint);
        $pdf->text($L + 10, $y + 8, 'HOW TO RETURN THIS ORDER', 8, true, $accent);
        $top = $y + 23;
        foreach ($steps as $n => $step) {
            $pdf->text($L + 10, $top, ($n + 1) . '.', 7.8, true, self::GREY);
            $top += $pdf->paragraph($L + 24, $top, $width - 34, $step, 7.8, false, self::INK) + 3;
        }

        // ----------------------------------------------------------- footers
        $total = $pdf->pageCount();
        for ($page = 0; $page < $total; $page++) {
            $pdf->usePage($page);
            $pdf->line($L, 806, self::R, 806, 0.5, self::RULE);
            $pdf->text($L, 811, sprintf('%s  ·  LPO form for offer %s  ·  prepared %s', $company, $code, date('j F Y')), 7, false, self::GREY);
            $pdf->text($L, 811, sprintf('Page %d of %d', $page + 1, $total), 7, false, self::GREY, 'right', $width);
        }

        return ['filename' => self::filename($offer), 'bytes' => $pdf->output()];
    }

    /** A new page with a short heading, for a form that runs over. Returns where to carry on. */
    private static function continued(FormPdf $pdf, string $company, array $accent): float
    {
        $pdf->addPage();
        $pdf->text(self::L, 36, $company, 10, true, self::INK);
        $pdf->text(self::L, 37, 'LOCAL PURCHASE ORDER (continued)', 9, true, $accent, 'right', self::R - self::L);
        $pdf->line(self::L, 54, self::R, 54, 1.2, $accent);

        return 64.0;
    }

    private static function cut(string $text, float $width, float $size): string
    {
        if (FormPdf::textWidth($text, $size) <= $width) {
            return $text;
        }
        while ($text !== '' && FormPdf::textWidth($text . '...', $size) > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '...';
    }
}
