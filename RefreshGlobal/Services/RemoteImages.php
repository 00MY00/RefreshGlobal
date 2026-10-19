<?php

namespace Modules\RefreshGlobal\Services;

/**
 * Remote images of the e-mails shown on a ticket page (tracking pixels: the sender learns that and when the message
 * was opened, from which device). FreeScout prints the message with FreeScout's filter "thread.body_output"
 * (resources/views/conversations/partials/thread.blade.php:297); here the remote images are neutralized before the
 * browser sees them, according to the settings:
 *
 *   show   always shown (FreeScout's behaviour)
 *   ask    not loaded; a "Show images" button per message loads them (the addresses stay in data-rg-* attributes)
 *   block  not loaded and can not be shown (the addresses are removed from the page)
 *
 * The mode is chosen per message from its origin tags (Services/MailTags.php): rules "tag = mode" of the settings
 * (exact tag, or a prefix ending with "*": "folder:clients-*"); when several rules match, the most restrictive wins;
 * no rule: the default mode. Images of the message itself (cid:, data:, attachments of this FreeScout) always show.
 */
class RemoteImages
{
    const SHOW = 'show';
    const ASK = 'ask';
    const BLOCK = 'block';
    /** transparent 1×1 GIF put in place of a remote image */
    const PIXEL = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /** @var array conversation id => [thread id => tags] (one query per ticket page) */
    protected static $tags = [];

    public static function modes()
    {
        return [self::SHOW, self::ASK, self::BLOCK];
    }

    protected static function rank($mode)
    {
        return array_search($mode, self::modes(), true);
    }

    public static function normalizeMode($mode, $default = self::ASK)
    {
        $mode = mb_strtolower(trim((string) $mode));

        return in_array($mode, self::modes(), true) ? $mode : $default;
    }

    /** "tag = mode" lines of the settings: [['tag' => tag or prefix*, 'mode' => mode]], invalid lines left out. */
    public static function rules($text = null)
    {
        $text = $text === null ? Settings::remoteImagesRules() : (string) $text;
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            list($tag, $mode) = array_map('trim', explode('=', $line, 2));
            $tag = mb_strtolower($tag);
            $mode = self::normalizeMode($mode, '');
            if ($mode !== '' && preg_match('/^[a-z0-9_:\-]{1,100}\*?$/', $tag)) {
                $out[] = ['tag' => $tag, 'mode' => $mode];
            }
        }

        return $out;
    }

    /** Mode of a message with these origin tags. */
    public static function modeFor(array $tags, array $rules = null, $default = null)
    {
        $rules = $rules === null ? self::rules() : $rules;
        $default = $default === null ? Settings::remoteImages() : self::normalizeMode($default);
        $found = null;
        foreach ($rules as $rule) {
            foreach ($tags as $tag) {
                $tag = mb_strtolower((string) $tag);
                $match = substr($rule['tag'], -1) === '*' ? strpos($tag, substr($rule['tag'], 0, -1)) === 0 : $tag === $rule['tag'];
                if ($match && ($found === null || self::rank($rule['mode']) > self::rank($found))) {
                    $found = $rule['mode'];
                }
            }
        }

        return $found !== null ? $found : $default;
    }

    /** Filter "thread.body_output": the body of one message as shown on the ticket page. */
    public static function filter($body, $thread = null, $conversation = null)
    {
        try {
            if (!is_string($body) || $body === '' || stripos($body, 'src') === false && stripos($body, 'url(') === false && stripos($body, 'background') === false) {
                return $body;
            }
            $mode = self::modeFor($thread ? self::tagsOf($thread, $conversation) : []);
            if ($mode === self::SHOW) {
                return $body;
            }
            list($html, $count) = self::neutralize($body, $mode === self::ASK);
            if (!$count) {
                return $body;
            }

            return self::banner($mode, $count).$html;
        } catch (\Throwable $e) {
            \Log::warning('[RefreshGlobal] [remote-images] '.$e->getMessage());

            return $body;
        }
    }

    /** Origin tags of the message (all the messages of the ticket read at once). */
    protected static function tagsOf($thread, $conversation)
    {
        if (!MailTags::tableExists()) {
            return [];
        }
        $cid = (int) ($conversation ? $conversation->id : $thread->conversation_id);
        if (!isset(self::$tags[$cid])) {
            $ids = \App\Thread::where('conversation_id', $cid)->pluck('id')->all();
            self::$tags[$cid] = [];
            foreach (MailTags::forThreads($ids) as $id => $row) {
                self::$tags[$cid][$id] = $row['tags'];
            }
        }

        return self::$tags[$cid][(int) $thread->id] ?? [];
    }

    public static function reset()
    {
        self::$tags = [];
    }

    /** True for an address on another site (http, https or protocol-relative), false for this FreeScout, cid:, data:. */
    public static function isRemote($url)
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
        if (!preg_match('#^(https?:)?//#i', $url)) {
            return false;
        }
        $host = parse_url((preg_match('#^//#', $url) ? 'https:' : '').$url, PHP_URL_HOST);
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);

        return !$host || !$own || mb_strtolower($host) !== mb_strtolower($own);
    }

    /**
     * Remote images of an HTML body neutralized: <img src / srcset>, "background" attributes, url(…) in style
     * attributes. $keep: the addresses are kept in data-rg-* attributes (mode "ask"), otherwise removed.
     *
     * @return array [html, number of remote images]
     */
    public static function neutralize($html, $keep = true)
    {
        $count = 0;
        $attr = function ($name, $value) {
            return ' '.$name.'="'.str_replace('"', '&quot;', $value).'"';
        };
        $html = preg_replace_callback('#<[a-z][a-z0-9]*\b[^>]*>#i', function ($m) use (&$count, $keep, $attr) {
            $tag = $m[0];
            $changed = false;
            // src / srcset (images), background (tables of old e-mails)
            $tag = preg_replace_callback('#\s(src|srcset|background)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', function ($a) use (&$count, &$changed, $keep, $attr) {
                $name = strtolower($a[1]);
                $value = isset($a[5]) ? $a[5] : (isset($a[4]) && $a[4] !== '' ? $a[4] : $a[3]);
                $remote = false;
                if ($name === 'srcset') {
                    foreach (explode(',', $value) as $part) {
                        $remote = $remote || self::isRemote(preg_split('/\s+/', trim($part))[0] ?? '');
                    }
                } else {
                    $remote = self::isRemote($value);
                }
                if (!$remote) {
                    return $a[0];
                }
                $changed = true;
                if ($name === 'src') {
                    $count++;

                    return $attr('src', self::PIXEL).($keep ? $attr('data-rg-src', $value) : '');
                }

                return $keep ? $attr('data-rg-'.$name, $value) : '';
            }, $tag);
            // url(…) in a style attribute
            $tag = preg_replace_callback('#\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\')#i', function ($a) use (&$count, &$changed, $keep, $attr) {
                $style = isset($a[3]) && $a[3] !== '' ? $a[3] : $a[2];
                $n = 0;
                $clean = preg_replace_callback('#url\(\s*(?:&quot;|["\'])?([^)"\']*)(?:&quot;|["\'])?\s*\)#i', function ($u) use (&$n) {
                    if (!self::isRemote($u[1])) {
                        return $u[0];
                    }
                    $n++;

                    return 'none';
                }, $style);
                if (!$n) {
                    return $a[0];
                }
                $count += $n;
                $changed = true;

                return $attr('style', $clean).($keep ? $attr('data-rg-style', $style) : '');
            }, $tag);
            if ($changed) {
                $tag = preg_replace('#^<([a-z][a-z0-9]*)#i', '<$1 data-rg-remote', $tag);
            }

            return $tag;
        }, $html);

        return [$html, $count];
    }

    protected static function banner($mode, $count)
    {
        $text = trans_choice('refreshglobal::messages.remote_images_blocked', $count, ['count' => $count]);
        $html = '<div class="rg-remote-images rg-ri-'.$mode.'" data-rg-ri><i class="glyphicon glyphicon-picture" aria-hidden="true"></i> '
            .'<span class="rg-ri-text">'.e($text).'</span>';
        if ($mode === self::ASK) {
            $html .= ' <button type="button" class="btn btn-default btn-xs rg-ri-show">'.e(__('refreshglobal::messages.remote_images_load')).'</button>';
        } else {
            $html .= ' <span class="rg-ri-note">'.e(__('refreshglobal::messages.remote_images_never')).'</span>';
        }

        return $html.'</div>';
    }
}
