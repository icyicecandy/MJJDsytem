<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Installation extends Model
{
    protected $fillable = [
        'application_id',
        'technician_id',
        'installation_date',
        'installation_time',
        'status',
        'completed_at',
        'installation_photo_path',
        'speedtest_photo_path',
        'nap_photo_path',
        'nap_code',
        'lp_code',
        'np_code',
        'full_nap_label',
        'port_number',
        'jo_number',
        'activation_date',
        'activation_status',
        'package_name',
        'technician_remarks',
        'router_serial',
        'cable_reel_no',
        'material_notes',
        'speed_test_result',
        'router_photo_path',
        'acceptance_report_photo_path',
        'cable_reel_photo_path',
        'customer_signature_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'installation_date' => 'date',
            'activation_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function technician()
    {
        return $this->belongsTo(\App\Models\User::class, 'technician_id');
    }

    public function displayJobReference(): string
    {
        $year = $this->installation_date?->format('Y')
            ?? $this->created_at?->format('Y')
            ?? now()->format('Y');

        return sprintf('JOB-%s-%04d', $year, $this->id);
    }

    public function displayStatusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status ?? 'unknown'));
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function activationDetails(): array
    {
        return [
            'NAP Code' => $this->nap_code,
            'LP Code' => $this->lp_code,
            'NP Code' => $this->np_code,
            'Full NAP Label' => $this->full_nap_label,
            'Port Number' => $this->port_number,
            'JO Number' => $this->jo_number,
            'Activation Date' => $this->activation_date?->format('F d, Y'),
            'Activation Status' => $this->activation_status,
            'Package' => $this->package_name,
            'Router Serial' => $this->router_serial,
            'Cable Reel No.' => $this->cable_reel_no,
            'Speed Test Result' => $this->speed_test_result,
        ];
    }

    public function proofPhotos(): array
    {
        return [
            'Router Photo' => $this->router_photo_path,
            'Speed Test Photo' => $this->speedtest_photo_path,
            'NAP Box Photo' => $this->nap_photo_path,
            'Acceptance Report Photo' => $this->acceptance_report_photo_path,
            'Cable Reel Photo' => $this->cable_reel_photo_path,
            'Installation Site Photo' => $this->installation_photo_path,
            'Customer Signature Photo' => $this->customer_signature_photo_path,
        ];
    }
}
