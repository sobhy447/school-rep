<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'journal_entry_id', 'file_name', 'stored_name', 'file_path', 'file_size', 'file_hash', 'total_pages', 'uploaded_by'];

    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
}
