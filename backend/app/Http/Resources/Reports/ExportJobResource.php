<?php

namespace App\Http\Resources\Reports;

use App\Models\Reports\ExportJob;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExportJob
 */
class ExportJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_key' => $this->report_key,
            'report_name' => app(ReportRegistry::class)->find($this->report_key)?->name(),
            'format' => $this->format,
            'params' => $this->params ?? (object) [],
            'status' => $this->status->value,
            'row_count' => $this->row_count,
            'error' => $this->error,
            'is_expired' => $this->isExpired(),
            'download_url' => $this->isDownloadable() ? '/api/v1/report-exports/'.$this->id.'/download' : null,
            'created_at' => $this->created_at?->toJSON(),
            'started_at' => $this->started_at?->toJSON(),
            'finished_at' => $this->finished_at?->toJSON(),
            'expires_at' => $this->expires_at?->toJSON(),
        ];
    }
}
