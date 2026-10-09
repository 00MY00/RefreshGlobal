<?php

namespace Modules\RefreshGlobal\Http\Controllers;

use Illuminate\Http\Request;
use Modules\RefreshGlobal\Entities\SavedView;
use Modules\RefreshGlobal\Services\Compatibility\Messages;
use Modules\RefreshGlobal\Services\GlobalTicketQuery;
use Modules\RefreshGlobal\Services\MailboxAccess;

/**
 * CSV export of the "All mailboxes" list with the active filters (GET /refresh-global/export).
 * Same query as the list (GlobalTicketQuery): only the allowed mailboxes, whatever the URL says.
 * Streamed, UTF-8 with BOM (Excel), number of rows capped (refreshglobal.export_max_rows).
 */
class ExportController extends Controller
{
    const CHUNK = 500;

    public function export(Request $request)
    {
        return $this->safely(function () use ($request) {
            $report = $this->report();
            if ($report['state'] === 'blocking') {
                return $this->blockedResponse(Messages::failed($report, ['blocking']));
            }
            $user = auth()->user();
            $access = new MailboxAccess($user);

            $input = $request->only(GlobalTicketsController::FILTER_PARAMS);
            if ($request->filled('view')) {
                try {
                    $view = SavedView::findForUser($request->input('view'), $user);
                } catch (\Exception $e) {
                    $view = null;
                }
                if ($view) {
                    $input = (array) $view->filters;
                }
            }
            $filters = GlobalTicketQuery::normalize($input, $access);
            $query = (new GlobalTicketQuery($access, $filters))->query();

            $max = max(1, (int) config('refreshglobal.export_max_rows', 5000));
            $delimiter = (string) config('refreshglobal.csv_delimiter', ';');
            $delimiter = in_array($delimiter, [';', ',', "\t"], true) ? $delimiter : ';';
            $filename = 'tickets-all-mailboxes-'.date('Y-m-d-His').'.csv';

            // dates in the user's own time zone (FreeScout profile), like on screen
            $tz = (string) ($user->timezone ?: config('app.timezone'));
            try {
                new \DateTimeZone($tz);
            } catch (\Exception $e) {
                $tz = (string) config('app.timezone');
            }

            return response()->stream(function () use ($query, $max, $delimiter, $tz) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
                fputcsv($out, self::header(), $delimiter);
                $written = 0;
                $page = 1;
                try {
                    while ($written < $max) {
                        $rows = (clone $query)->with(['mailbox', 'customer', 'user'])
                            ->forPage($page, self::CHUNK)->get();
                        if (!count($rows)) {
                            break;
                        }
                        foreach ($rows as $conversation) {
                            if ($written >= $max) {
                                break;
                            }
                            fputcsv($out, self::row($conversation, $tz), $delimiter);
                            $written++;
                        }
                        if (count($rows) < self::CHUNK) {
                            break;
                        }
                        $page++;
                    }
                } catch (\Throwable $e) {
                    // headers are already sent: the error is written in the file and in the log
                    \Log::error('[RefreshGlobal] [RG-ERR-01] CSV export interrupted: '.$e->getMessage());
                    fputcsv($out, ['[RG-ERR-01] '.__('refreshglobal::messages.export_interrupted')], $delimiter);
                }
                fclose($out);
            }, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'no-store, no-cache',
                'X-RefreshGlobal-Max-Rows' => (string) $max,
            ]);
        });
    }

    public static function header()
    {
        return [
            __('refreshglobal::messages.csv_number'),
            __('refreshglobal::messages.csv_mailbox'),
            __('refreshglobal::messages.csv_subject'),
            __('refreshglobal::messages.csv_status'),
            __('refreshglobal::messages.csv_assignee'),
            __('refreshglobal::messages.csv_customer'),
            __('refreshglobal::messages.csv_email'),
            __('refreshglobal::messages.csv_created'),
            __('refreshglobal::messages.csv_last_reply'),
            __('refreshglobal::messages.csv_closed'),
            __('refreshglobal::messages.csv_url'),
        ];
    }

    public static function row($c, $tz = null)
    {
        $tz = $tz ?: config('app.timezone');
        $date = function ($value) use ($tz) {
            if (!$value) {
                return '';
            }
            try {
                // FreeScout stores dates in the application time zone (config/app.php 'timezone')
                return \Carbon\Carbon::parse($value, config('app.timezone'))->setTimezone($tz)->format('Y-m-d H:i');
            } catch (\Exception $e) {
                return '';
            }
        };

        return array_map([self::class, 'cell'], [
            $c->number,
            $c->mailbox ? $c->mailbox->name : '',
            $c->getSubject(),
            $c->getStatusName(),
            $c->user ? $c->user->getFullName() : '',
            $c->customer ? $c->customer->getFullName() : '',
            $c->customer_email,
            $date($c->created_at),
            $date($c->last_reply_at),
            $date($c->closed_at),
            $c->url(),
        ]);
    }

    /** Protection against CSV formula injection: a cell starting with = + - @ is prefixed with a quote. */
    public static function cell($value)
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
