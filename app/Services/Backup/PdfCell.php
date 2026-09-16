<?php

namespace App\Services\Backup;

/**
 * A table cell whose HTML is built by BackupPdfService itself (money spans,
 * date spans) and is therefore trusted. table() renders these raw while
 * every plain cell stays e()-escaped — the escaping protocol that keeps
 * "<span dir=\"ltr\">414.00 $</span>" from printing as literal text.
 */
final class PdfCell
{
    public function __construct(
        public readonly string $html,
    ) {}

    public function __toString(): string
    {
        return $this->html;
    }
}
