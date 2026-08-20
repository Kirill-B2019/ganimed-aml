<?php

// | KB @CerberRus00 - Nexus Invest Team
namespace App\Services\Reports;

use App\Models\Check;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class CheckPdfService
{
    public function __construct(private CheckReportPresenter $presenter) {}

    public function download(Check $check): Response
    {
        $check->loadMissing('user');

        $pdf = Pdf::loadHTML($this->html($check))
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download(sprintf('aml-check-%d.pdf', $check->id));
    }

    public function html(Check $check): string
    {
        $locale = $this->activeLocale();
        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            return view('reports.check', $this->presenter->data($check, true))->render();
        } finally {
            app()->setLocale($previous);
        }
    }

    private function activeLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, ['en', 'ru'], true) ? $locale : 'en';
    }
}
