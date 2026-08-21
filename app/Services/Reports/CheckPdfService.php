<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Reports;

use App\Enums\CheckType;
use App\Models\Check;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class CheckPdfService
{
    public const VARIANT_FILE = 'file';

    public const VARIANT_FULL = 'full';

    public function __construct(private CheckReportPresenter $presenter) {}

    public function download(Check $check, string $variant = self::VARIANT_FULL): Response
    {
        $check->loadMissing('user', 'previousCheck');
        $variant = $this->normalize($variant, $check);

        $pdf = Pdf::loadHTML($this->html($check, $variant))
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($this->filename($check, $variant));
    }

    public function filename(Check $check, string $variant = self::VARIANT_FULL): string
    {
        $subject = preg_replace('/[^A-Za-z0-9]+/', '-', (string) $check->subject) ?? '';
        $subject = trim($subject, '-');
        if ($subject === '') {
            $subject = 'check-'.$check->id;
        }

        return $subject.'_'.now()->format('Y-m-d_H-i-s').'_'.$this->normalize($variant, $check).'.pdf';
    }

    public function html(Check $check, string $variant = self::VARIANT_FULL): string
    {
        $locale = $this->activeLocale();
        $previous = app()->getLocale();
        app()->setLocale($locale);
        $variant = $this->normalize($variant, $check);

        try {
            return view('reports.check', $this->presenter->data(
                $check,
                true,
                $variant === self::VARIANT_FILE,
            ))->render();
        } finally {
            app()->setLocale($previous);
        }
    }

    public function normalize(string $variant, ?Check $check = null): string
    {
        if ($check?->type === CheckType::Token) {
            return self::VARIANT_FILE;
        }

        return $variant === self::VARIANT_FILE ? self::VARIANT_FILE : self::VARIANT_FULL;
    }

    private function activeLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, ['en', 'ru'], true) ? $locale : 'en';
    }
}
