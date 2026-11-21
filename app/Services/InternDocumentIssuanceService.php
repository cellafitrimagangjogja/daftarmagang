<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\DocumentTemplate;
use App\Models\GeneratedDocument;
use App\Models\Institution;
use App\Models\InternAssessment;
use App\Models\InternCertificate;
use App\Models\InternExtra;
use App\Models\InternLoa;
use App\Models\InternshipRegistration;
use App\Models\SklDocument;
use App\Models\SystemAsset;
use App\Models\User;
use App\Services\DocumentTemplates\DocumentTemplateRenderer;
use App\Services\DocumentTemplates\MailMergePlaceholderContract;
use App\Services\MailMerge\VariableRegistry;
use App\Services\MailMerge\VariableResolverService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InternDocumentIssuanceService
{
    public const TYPE_LOA            = 'loa';
    public const TYPE_SKL            = 'skl';
    public const TYPE_CERTIFICATE    = 'certificate';
    public const TYPE_ASSESSMENT     = 'assessment';
    public const TYPE_RECOMMENDATION = 'recommendation';

    public function __construct(
        protected DocumentPublishingService $publishingService,
        protected DocumentGeneratorService $docGeneratorService,
        protected VariableResolverService $resolverService,
        protected SystemAssetService $assetService
    ) {}

    /**
     * Normalisasi alias jenis dokumen ke tipe kanonikal sistem.
     */
    public static function normalizeType(string $type): string
    {
        $t = strtolower(trim($type));
        return match ($t) {
            'sertifikat'  => self::TYPE_CERTIFICATE,
            'penilaian'   => self::TYPE_ASSESSMENT,
            'rekomendasi' => self::TYPE_RECOMMENDATION,
            default       => $t,
        };
    }

    /**
     * Periksa apakah status pemagang memenuhi syarat penerbitan dokumen tertentu.
     */
    public function canIssue(InternshipRegistration $intern, string $documentType): bool
    {
        $canonical = self::normalizeType($documentType);
        $status = strtolower((string) ($intern->internship_status ?? ''));

        return match ($canonical) {
            self::TYPE_LOA => in_array($status, [
                InternshipRegistration::STATUS_ACCEPTED,
                InternshipRegistration::STATUS_ACTIVE,
                InternshipRegistration::STATUS_COMPLETED,
            ], true),
            self::TYPE_SKL,
            self::TYPE_CERTIFICATE,
            self::TYPE_ASSESSMENT,
            self::TYPE_RECOMMENDATION => in_array($status, [
                InternshipRegistration::STATUS_COMPLETED,
            ], true),
            default => false,
        };
    }

    /**
     * Resolusi template aktif yang dipilih Admin untuk jenis dokumen terkait.
     * Jika failClosed = true, larang keras pembuatan preset transien/tersembunyi.
     */
    public function resolveActiveTemplate(string $documentType, bool $failClosed = false): ?DocumentTemplate
    {
        $canonical = self::normalizeType($documentType);

        // 1. Ambil template default yang aktif
        $template = DocumentTemplate::ofType($canonical)
            ->active()
            ->default()
            ->first();

        // 2. Jika tidak ada default, ambil template aktif manapun
        if (!$template) {
            $template = DocumentTemplate::ofType($canonical)
                ->active()
                ->latest('id')
                ->first();
        }

        // 3. Fallback: Fail-Closed pada penerbitan resmi atau simulasi non-resmi pada preview
        if (!$template) {
            if ($failClosed) {
                return null;
            }

            // Fallback HANYA untuk isolasi preview non-resmi saat draf belum dibuat
            $isCanvas = ($canonical === self::TYPE_CERTIFICATE);
            $template = new DocumentTemplate([
                'uuid'           => (string) Str::uuid(),
                'type'           => $canonical,
                'name'           => 'Simulasi Template ' . strtoupper($canonical),
                'editor_type'    => $isCanvas ? 'visual_canvas' : 'visual_html',
                'content_html'   => $isCanvas ? null : DocumentTemplate::getDefaultStandardContent($canonical),
                'canvas_layout'  => $isCanvas ? DocumentTemplate::getDefaultCertificateCanvasLayout() : null,
                'page_config'    => DocumentTemplate::getDefaultPageConfig($canonical),
                'is_active'      => false, // Simulasi non-aktif
                'is_default'     => false,
                'version_number' => 1,
            ]);
        }

        return $template;
    }

    /**
     * Resolusi nomor dokumen yang sudah pernah diterbitkan sebelumnya (GeneratedDocument atau tabel legacy).
     */
    public function resolveExistingDocumentNumber(InternshipRegistration $intern, string $documentType): ?string
    {
        $canonical = self::normalizeType($documentType);

        // 1. Cek dari GeneratedDocument yang sudah ada
        $existingGen = GeneratedDocument::select(['id', 'document_number'])
            ->where('internship_registration_id', $intern->id)
            ->where('document_type', $canonical)
            ->whereNotNull('document_number')
            ->latest('id')
            ->value('document_number');

        if (!empty($existingGen)) {
            return $existingGen;
        }

        // 2. Cek dari tabel legacy historis jika pernah diterbitkan
        return match ($canonical) {
            self::TYPE_LOA            => InternLoa::where('intern_id', $intern->id)->value('loa_number'),
            self::TYPE_SKL            => SklDocument::where('intern_id', $intern->id)->value('skl_number'),
            self::TYPE_CERTIFICATE    => InternCertificate::where('intern_id', $intern->id)->value('certificate_number'),
            self::TYPE_ASSESSMENT     => InternAssessment::where('intern_id', $intern->id)->value('assessment_number'),
            self::TYPE_RECOMMENDATION => InternExtra::where('intern_id', $intern->id)->value('letter_number'),
            default                   => null,
        };
    }

    /**
     * Bangun konteks variabel dan referensi aset sistem untuk dokumen pemagang.
     */
    public function buildContext(
        InternshipRegistration $intern,
        string $documentType,
        array $options = [],
        bool $isLiveIssuance = false
    ): array {
        $canonical = self::normalizeType($documentType);
        $intern->load(['brandRel', 'division', 'institution', 'studyProgramRel', 'user']);

        $selectedBrandId = !empty($options['brand_id']) ? (int) $options['brand_id'] : null;
        $brandModel = ($selectedBrandId ? Brand::find($selectedBrandId) : null)
            ?? $intern->brandRel
            ?? ($intern->brand_id ? Brand::find($intern->brand_id) : null)
            ?? Brand::getDefaultBrand();
        $instModel  = $intern->institution;

        // 1. Ekstraksi nomor dokumen: utamakan nomor custom dari options, nomor historis existing, baru generate nomor baru
        $docNumber = $options['document_number'] ?? $this->resolveExistingDocumentNumber($intern, $canonical);
        if (empty($docNumber)) {
            $docNumber = match ($canonical) {
                self::TYPE_LOA            => $this->docGeneratorService->generateUniqueLoaNumber($intern, $brandModel),
                self::TYPE_SKL            => $this->docGeneratorService->generateUniqueSklNumber($intern, $brandModel),
                self::TYPE_CERTIFICATE    => $this->generateUniqueCertificateNumber($intern),
                self::TYPE_ASSESSMENT     => $this->generateUniqueAssessmentNumber($intern),
                self::TYPE_RECOMMENDATION => $this->generateUniqueRecommendationNumber($intern),
                default                   => strtoupper($canonical) . '/' . date('Ymd') . '/' . $intern->id,
            };
        }

        // 2. Resolusi dasar dari VariableResolverService
        $baseContext = $this->resolverService->resolveContext(
            intern: $intern,
            institution: $instModel,
            brand: $brandModel,
            overrides: $options['variables'] ?? [],
            assetOverrides: $options['asset_overrides'] ?? [],
            isLiveIssuance: $isLiveIssuance,
            isPreviewMock: !$isLiveIssuance,
            documentType: $canonical
        );

        $variables = $baseContext['variables'];
        $assetReferences = $baseContext['asset_references'];

        // 3. Tambahkan variabel-variabel detail spesifik dokumen
        $companyName = $brandModel?->name ?? ($intern->brand ?: ($isLiveIssuance ? 'Seven Inc' : 'Seven Inc'));
        $companyAddress = $brandModel?->company_address ?? ($brandModel?->address ?? 'Jl. Janti Gg. Arjuna No. 59, Karangjambe, Banguntapan, Bantul, Yogyakarta 55198');
        if (empty(trim((string)$companyAddress)) || $companyAddress === '-') {
            $companyAddress = 'Jl. Janti Gg. Arjuna No. 59, Karangjambe, Banguntapan, Bantul, Yogyakarta 55198';
        }
        
        // Pertahankan nama yang tersimpan di dokumen, lalu gunakan profil brand
        // terkait. String kosong diperlakukan sama seperti null agar tidak
        // mengosongkan placeholder penandatangan saat PDF dirender ulang.
        $storedDocument = match ($canonical) {
            self::TYPE_SKL => SklDocument::where('intern_id', $intern->id)->latest('id')->first(),
            self::TYPE_ASSESSMENT => InternAssessment::where('intern_id', $intern->id)->latest('id')->first(),
            default => null,
        };
        $firstNonEmpty = static function (...$values): ?string {
            foreach ($values as $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
            return null;
        };
        $signatoryName = $firstNonEmpty(
            $options['signatory_name'] ?? null,
            $brandModel?->signatory_name,
            $storedDocument?->signatory_name
        );
        $signatoryPosition = $firstNonEmpty(
            $options['signatory_position'] ?? null,
            $brandModel?->signatory_position,
            $storedDocument?->signatory_position
        ) ?? 'HRD';

        if ($canonical === self::TYPE_RECOMMENDATION) {
            if ($isLiveIssuance) {
                $signatoryName = $brandModel?->signatory_name;
                $signatoryPosition = $brandModel?->signatory_position;
            } else {
                $signatoryName = $brandModel?->signatory_name ?: 'Rekario Danny Sanjaya, S.Kom.';
                $signatoryPosition = $brandModel?->signatory_position ?: 'CEO';
            }
        }

        $startDateStr = $intern->start_date ? Carbon::parse($intern->start_date)->locale('id')->translatedFormat('d F Y') : ($isLiveIssuance ? '' : '-');
        $endDateStr   = $intern->end_date ? Carbon::parse($intern->end_date)->locale('id')->translatedFormat('d F Y') : ($isLiveIssuance ? '' : '-');

        $durationStr = $isLiveIssuance ? '' : 'beberapa bulan';
        if ($intern->start_date && $intern->end_date) {
            $months = (int) round(Carbon::parse($intern->start_date)->diffInDays(Carbon::parse($intern->end_date)) / 30);
            $durationStr = max(1, $months) . ' bulan';
        }

        // Resolusi Nilai Asli dari InternAssessment (bukan hardcoded 85.0 / Sangat Baik)
        $assessment = InternAssessment::where('intern_id', $intern->id)->latest('id')->first();
        $assessmentComponents = $options['aspek_penilaian'] ?? $assessment?->aspek_penilaian ?? [];
        // The aspect rows are the source of truth. Recalculate here so old or
        // stale rata_rata values can never override the scores shown in the form.
        $assessmentAverage = $this->calculateAssessmentAverage($assessmentComponents)
            ?? ($options['rata_rata'] ?? $assessment?->rata_rata);
        if ($assessmentAverage !== null) {
            $rataRata = (string) $assessmentAverage;
            $predikat = ($assessmentAverage >= 85) ? 'Sangat Baik (A)' : (($assessmentAverage >= 75) ? 'Baik (B)' : 'Cukup (C)');
        } else {
            if ($isLiveIssuance) {
                $rataRata = $options['rata_rata'] ?? null;
                $predikat = $options['predikat'] ?? null;
            } else {
                $rataRata = $options['rata_rata'] ?? '85.0';
                $predikat = $options['predikat'] ?? 'Sangat Baik (A)';
            }
        }

        // Variabel tambahan
        $additionalVars = [
            'dokumen.nomor'                 => $docNumber,
            'dokumen.tanggal'               => now()->translatedFormat('d F Y'),
            'dokumen.tempat'                => 'Sleman',
            'dokumen.penandatangan_nama'    => $signatoryName,
            'dokumen.penandatangan_jabatan' => $signatoryPosition,
            'brand.penandatangan.nama'      => $signatoryName,
            'brand.penandatangan.jabatan'   => $signatoryPosition,
            'perusahaan.pimpinan'           => $signatoryName,
            'perusahaan.jabatan'            => $signatoryPosition,
            'perusahaan.nama'               => $companyName,
            'perusahaan.alamat'             => $companyAddress,
            'brand.nama'                    => $companyName,
            'brand.alamat'                  => $companyAddress,
            'perusahaan.kota'               => 'Sleman',
            'brand.kota'                    => 'Sleman',
            'perusahaan.telepon'            => $brandModel?->phone ?? ($isLiveIssuance ? '0274-4534571' : '0274-4534571'),
            'perusahaan.kodepos'            => '55198',
            'pemagang.nama'                 => $intern->fullname ?? ($isLiveIssuance ? '' : 'Nama Pemagang'),
            'pemagang.nim'                  => ($intern->student_id ?? $intern->nim_nis ?? $intern->nim ?? '-') ?: '-',
            'pemagang.institusi'            => $intern->institution_name ?? ($instModel?->name ?? ($isLiveIssuance ? '' : '-')),
            'pemagang.prodi'                => $intern->study_program ?? ($intern->major ?? '-'),
            'pemagang.divisi'               => $intern->division?->name ?? ($intern->internship_interest ?? ($isLiveIssuance ? '' : 'Umum')),
            'magang.divisi'                 => $intern->division?->name ?? ($intern->internship_interest ?? ($isLiveIssuance ? '' : 'Umum')),
            'pemagang.brand'                => $companyName,
            'pemagang.tanggal_mulai'        => $startDateStr,
            'pemagang.tanggal_selesai'      => $endDateStr,
            'magang.tanggal_mulai'          => $startDateStr,
            'magang.tanggal_selesai'        => $endDateStr,
            'pemagang.periode'              => ($startDateStr && $endDateStr) ? "{$startDateStr} - {$endDateStr}" : '-',
            'pemagang.durasi'               => $durationStr,
            'magang.durasi'                 => $durationStr,
            'penilaian.nilai_akhir'         => $rataRata,
            'penilaian.predikat'            => $predikat,
            'penilaian.aspek_data'          => $assessmentComponents,
            'penilaian.tabel_komponen'      => $this->renderAssessmentComponentsTable($assessmentComponents),
            'nilai.rata_rata'               => $rataRata,
            'nilai.predikat'                => $predikat,
            'rekomendasi.isi'               => $options['body_text'] ?? $this->getDefaultRecommendationBody($intern, $companyName, $durationStr),
        ];

        foreach ($additionalVars as $k => $v) {
            if ($v !== null && $v !== '' && $v !== '-') {
                $variables[$k] = $v;
            } elseif (!isset($variables[$k])) {
                $variables[$k] = $v;
            }
        }

        // Resolusi aset background sertifikat jika tipe sertifikat
        if ($canonical === self::TYPE_CERTIFICATE) {
            $bgAsset = $this->assetService->resolveAsset(
                category: SystemAsset::CATEGORY_BACKGROUND,
                assetCode: 'asset.background',
                brandId: $brandModel?->id
            );

            if ($bgAsset) {
                $assetReferences['asset.background'] = [
                    'asset_id' => $bgAsset->id,
                    'name'     => $bgAsset->name,
                    'version'  => $bgAsset->version,
                    'checksum' => $bgAsset->checksum_sha256,
                    'category' => $bgAsset->category,
                ];
                $bgDataUri = $this->assetService->getAssetAsBase64DataUri($bgAsset);
                $variables['asset.background'] = $bgDataUri;
            }
        }

        // Resolusi aset fisik fallback jika token gambar masih belum terisi (INV-05 / fail-safe)
        $resolveImageHelper = function (...$paths) {
            foreach ($paths as $path) {
                if (empty($path)) continue;
                $cleanPath = ltrim($path, '/');
                if (str_starts_with($cleanPath, 'storage/')) {
                    $cleanPath = substr($cleanPath, 8);
                }
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)) {
                    $content = \Illuminate\Support\Facades\Storage::disk('public')->get($cleanPath);
                    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($cleanPath) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode($content);
                }
                if (file_exists($path) && is_file($path)) {
                    $mime = mime_content_type($path) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
                $inPub = public_path(ltrim($path, '/'));
                if (file_exists($inPub) && is_file($inPub)) {
                    $mime = mime_content_type($inPub) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($inPub));
                }
                $inStorage = storage_path('app/public/' . ltrim($path, '/'));
                if (file_exists($inStorage) && is_file($inStorage)) {
                    $mime = mime_content_type($inStorage) ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($inStorage));
                }
            }
            return null;
        };

        // 1. Logo Perusahaan / Brand
        if (!empty($brandModel?->logo)) {
            $logoData = $resolveImageHelper($brandModel->logo);
            if ($logoData) {
                $variables['asset.company.logo'] = $logoData;
                $variables['asset.brand.logo']   = $logoData;
                $variables['company_logo']       = $logoData;
                $variables['brand_logo']         = $logoData;
            }
        } elseif (!$isLiveIssuance && empty($variables['asset.company.logo']) && empty($variables['asset.brand.logo'])) {
            $logoData = $resolveImageHelper(
                'images/mou/seven-inc-logo.png',
                'images/logos/logo_seveninc.png'
            );
            if ($logoData) {
                $variables['asset.company.logo'] = $logoData;
                $variables['asset.brand.logo']   = $logoData;
            }
        } else {
            $variables['asset.company.logo'] = $variables['asset.company.logo'] ?? null;
            $variables['asset.brand.logo']   = $variables['asset.brand.logo'] ?? null;
        }

        // Logo institusi pendaftar juga tersedia langsung pada tabel institutions.
        // Gunakan sebagai fallback jika SystemAsset belum tersinkron/terdaftar agar
        // template yang memakai token logo institusi tetap membawa gambar ke PDF.
        if (empty($variables['asset.institusi.logo']) && !empty($instModel?->logo)) {
            $institutionLogoData = $resolveImageHelper($instModel->logo);
            if ($institutionLogoData) {
                $variables['asset.institusi.logo'] = $institutionLogoData;
                $variables['asset.institution.logo'] = $institutionLogoData;
            }
        }

        // 2. Tanda Tangan Penandatangan
        if (!empty($brandModel?->signature)) {
            $sigData = $resolveImageHelper($brandModel->signature);
            if ($sigData) {
                $variables['asset.signature']          = $sigData;
                $variables['asset.brand.tanda_tangan'] = $sigData;
                $variables['signature']                = $sigData;
                $variables['tanda_tangan']             = $sigData;
            }
        } elseif (!$isLiveIssuance && empty($variables['asset.signature']) && empty($variables['asset.brand.tanda_tangan'])) {
            $sigData = $resolveImageHelper(
                'images/mou/signature-sample.png',
                'images/signature/ttd_arisetiahusbana.png',
                'images/signatures/ttd.png'
            );
            if ($sigData) {
                $variables['asset.signature']          = $sigData;
                $variables['asset.brand.tanda_tangan'] = $sigData;
            }
        } else {
            $variables['asset.signature']          = null;
            $variables['asset.brand.tanda_tangan'] = null;
            $variables['signature']                = null;
            $variables['tanda_tangan']             = null;
        }

        // 3. Stempel Perusahaan
        if (!empty($brandModel?->stamp)) {
            $stampData = $resolveImageHelper($brandModel->stamp);
            if ($stampData) {
                $variables['asset.stamp']         = $stampData;
                $variables['asset.brand.stempel'] = $stampData;
                $variables['asset.company.stamp'] = $stampData;
                $variables['stamp']               = $stampData;
                $variables['stempel']             = $stampData;
            }
        } elseif (!$isLiveIssuance && empty($variables['asset.stamp']) && empty($variables['asset.brand.stempel'])) {
            $stampData = $resolveImageHelper(
                'images/mou/stamp-sample.png',
                'images/stamps/stamp.png'
            );
            if ($stampData) {
                $variables['asset.stamp']         = $stampData;
                $variables['asset.brand.stempel'] = $stampData;
                $variables['asset.company.stamp'] = $stampData;
                $variables['stamp']               = $stampData;
                $variables['stempel']             = $stampData;
            }
        } else {
            $variables['asset.stamp']         = null;
            $variables['asset.brand.stempel'] = null;
            $variables['asset.company.stamp'] = null;
            $variables['stamp']               = null;
            $variables['stempel']             = null;
        }

        // 4. Background Sertifikat
        if (empty($variables['asset.background'])) {
            $bgData = $resolveImageHelper(
                $brandModel?->internship_certificate_bg,
                'images/certificate/certificate-bg.png',
                'images/backgrounds/cert_bg.png'
            );
            if ($bgData) {
                $variables['asset.background'] = $bgData;
                if (!empty($brandModel?->internship_certificate_bg)) {
                    $variables['force_brand_background'] = true;
                }
            }
        }

        return [
            'variables'        => $variables,
            'asset_references' => $assetReferences,
            'document_number'  => $docNumber,
        ];
    }

    /** Render rincian skor yang ditempatkan template melalui token penilaian.tabel_komponen. */
    private function renderAssessmentComponentsTable(mixed $components): string
    {
        if (!is_array($components) || $components === []) {
            return '';
        }

        $defaultWeight = 100 / count($components);
        $weightTotal = array_sum(array_map(
            static fn ($component) => is_array($component) ? max(0, (float) ($component['bobot'] ?? $defaultWeight)) : 0,
            $components
        ));
        $rows = '';
        foreach ($components as $index => $component) {
            if (!is_array($component)) {
                continue;
            }
            $name = htmlspecialchars((string) ($component['aspek'] ?? ''), ENT_QUOTES, 'UTF-8');
            $score = htmlspecialchars((string) ($component['nilai'] ?? ''), ENT_QUOTES, 'UTF-8');
            $weight = max(0, (float) ($component['bobot'] ?? $defaultWeight));
            $weightLabel = htmlspecialchars(rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.'), ENT_QUOTES, 'UTF-8');
            $contribution = $weightTotal > 0
                ? number_format((float) ($component['nilai'] ?? 0) * $weight / $weightTotal, 2, '.', '')
                : '0.00';
            $rows .= '<tr><td>' . ((int) $index + 1) . '</td><td>' . $name . '</td><td>' . $weightLabel . '%</td><td>' . $score . '</td><td>' . $contribution . '</td></tr>';
        }

        if ($rows === '') {
            return '';
        }

        return '<table class="assessment-components"><thead><tr><th>No</th><th>Aspek Penilaian</th><th>Bobot</th><th>Nilai</th><th>Kontribusi</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>';
    }

    /** Hitung rata-rata berbobot dari aspek yang tersimpan. */
    private function calculateAssessmentAverage(mixed $components): ?float
    {
        if (!is_array($components) || $components === []) {
            return null;
        }

        $defaultWeight = 100 / count($components);
        $weightedTotal = 0.0;
        $weightTotal = 0.0;
        foreach ($components as $component) {
            if (!is_array($component) || !is_numeric($component['nilai'] ?? null)) {
                continue;
            }
            $weight = max(0, (float) ($component['bobot'] ?? $defaultWeight));
            $weightedTotal += (float) $component['nilai'] * $weight;
            $weightTotal += $weight;
        }

        return $weightTotal > 0 ? round($weightedTotal / $weightTotal, 2) : null;
    }

    /**
     * Preview dokumen pemagang secara terisolasi.
     * JAMINAN: TIDAK menulis ke GeneratedDocument, TIDAK menyimpan berkas final, TIDAK memicu Drive sync.
     */
    public function previewDocument(
        InternshipRegistration $intern,
        string $documentType,
        array $options = []
    ): array {
        $canonical = self::normalizeType($documentType);

        $template = !empty($options['template_id'])
            ? DocumentTemplate::findOrFail($options['template_id'])
            : $this->resolveActiveTemplate($canonical);

        $context = $this->buildContext($intern, $canonical, $options);
        $variables = $context['variables'];

        if ($template->type === self::TYPE_CERTIFICATE || $template->editor_type === 'visual_canvas') {
            $html = DocumentTemplateRenderer::renderCertificateHtml($template, $variables);
        } else {
            $html = DocumentTemplateRenderer::renderLetterHtml($template, $variables);
        }

        return [
            'html'             => $html,
            'template'         => $template,
            'document_type'    => $canonical,
            'document_number'  => $context['document_number'],
            'variables'        => $variables,
            'asset_references' => $context['asset_references'],
        ];
    }

    /**
     * Terbitkan dokumen pemagang secara resmi.
     * Menggunakan template aktif, render DomPDF, simpan berkas fisik,
     * catat immutable snapshot & sha256 checksum ke GeneratedDocument,
     * dan sinkronisasikan ke subfolder Drive pemagang existing tanpa duplikasi folder.
     */
    public function issueDocument(
        InternshipRegistration $intern,
        string $documentType,
        array $options = [],
        ?User $actor = null
    ): GeneratedDocument {
        $canonical = self::normalizeType($documentType);

        // 1. Validasi status pemagang
        if (!$this->canIssue($intern, $canonical)) {
            throw new InvalidArgumentException(
                "Status pemagang '{$intern->internship_status}' belum memenuhi syarat untuk penerbitan dokumen {$canonical}."
            );
        }

        // 2. Resolve template aktif (Fail-Closed: larangan hidden preset pada penerbitan resmi)
        $template = !empty($options['template_id'])
            ? DocumentTemplate::findOrFail($options['template_id'])
            : $this->resolveActiveTemplate($canonical, failClosed: true);

        if (!$template || !$template->exists) {
            throw new InvalidArgumentException(
                "Belum ada template aktif untuk jenis dokumen '{$canonical}'. Penerbitan resmi ditolak hingga template diaktifkan."
            );
        }

        // Validasi kesesuaian tipe template & status aktif untuk penerbitan resmi
        if ($template->type !== $canonical && !($canonical === self::TYPE_CERTIFICATE && $template->type === 'certificate')) {
            throw new InvalidArgumentException("Tipe template '{$template->type}' tidak sesuai dengan jenis dokumen '{$canonical}'.");
        }
        if (!$template->is_active) {
            throw new InvalidArgumentException("Template '{$template->name}' sedang tidak aktif.");
        }

        $isRevision = !empty($options['is_revision']) || (($options['action'] ?? '') === 'revision');

        return DB::transaction(function () use ($intern, $canonical, $options, $actor, $isRevision, $template) {
            // Row lock untuk mencegah race condition concurrent issuance
            InternshipRegistration::where('id', $intern->id)->lockForUpdate()->first();

            // Hitung nomor versi revisi jika ini penerbitan revisi resmi
            $revisionVersion = 1;
            if ($isRevision) {
                if (!empty($options['revision_version'])) {
                    $revisionVersion = (int) $options['revision_version'];
                } else {
                    // Cek apakah ada revisi yang baru saja diterbitkan (debounce < 60 detik) untuk menangkal double-click / concurrent requests
                    $recentRevision = GeneratedDocument::select(['id', 'template_version', 'created_at'])
                        ->where('internship_registration_id', $intern->id)
                        ->where('document_type', $canonical)
                        ->where('template_version', '>', 1)
                        ->latest('id')
                        ->first();

                    if ($recentRevision && $recentRevision->created_at && $recentRevision->created_at->diffInSeconds(now()) <= 60) {
                        $revisionVersion = (int) $recentRevision->template_version;
                    } else {
                        $maxVersion = GeneratedDocument::where('internship_registration_id', $intern->id)
                            ->where('document_type', $canonical)
                            ->max('template_version') ?? 1;
                        $revisionVersion = (int) $maxVersion + 1;
                    }
                }

                if (empty($options['document_number'])) {
                    $baseDocNumber = $this->resolveExistingDocumentNumber($intern, $canonical);
                    if (!empty($baseDocNumber)) {
                        if (preg_match('/\/REV\.\d+$/', $baseDocNumber)) {
                            $options['document_number'] = preg_replace('/\/REV\.\d+$/', '/REV.' . $revisionVersion, $baseDocNumber);
                        } elseif (!str_contains($baseDocNumber, '/REV')) {
                            $options['document_number'] = $baseDocNumber . '/REV.' . $revisionVersion;
                        }
                    }
                }
            }

            // 3. Bangun konteks variabel dan aset resmi (tanpa nilai tiruan/mock)
            $context = $this->buildContext($intern, $canonical, $options, isLiveIssuance: true);
            $variables = $context['variables'];
            $assetReferences = $context['asset_references'];
            $docNumber = $context['document_number'];

            // 4. Diagnostik Variabel Wajib (V04 & Gate Fail-Closed)
            $usedTokens = DocumentTemplateRenderer::extractTokens($template);
            $diagnostics = $this->resolverService->diagnoseMissingVariables(
                usedTokens: $usedTokens,
                resolvedVariables: $variables,
                documentType: $canonical
            );

            if (!$diagnostics['valid']) {
                $errorMessages = [];
                if (!empty($diagnostics['unknown_tokens'])) {
                    $errorMessages[] = "Ditemukan placeholder tidak dikenal atau salah ketik: " . implode(', ', $diagnostics['unknown_tokens']);
                }
                if (!empty($diagnostics['missing_required'])) {
                    $missingFields = array_map(
                        fn($item) => "{$item['label']} ({$item['token']})",
                        $diagnostics['missing_required']
                    );
                    $errorMessages[] = "Variabel wajib belum lengkap: " . implode(', ', $missingFields);
                }

                $detailStr = implode('; ', $errorMessages);
                throw new InvalidArgumentException(
                    "Penerbitan dokumen {$canonical} ditolak: {$detailStr}."
                );
            }

            // 4b. Perlindungan kelengkapan profil operasional brand (Logo & Profil Kantor)
            $intern->loadMissing('brandRel');
            $selectedBrandId = !empty($options['brand_id']) ? (int) $options['brand_id'] : null;
            $brandModel = ($selectedBrandId ? Brand::find($selectedBrandId) : null)
                ?? $intern->brandRel
                ?? ($intern->brand_id ? Brand::find($intern->brand_id) : null)
                ?? Brand::getDefaultBrand();
            if ($brandModel && !$brandModel->isProfileCompleteForDocuments()) {
                $missing = implode(', ', $brandModel->getMissingDocumentFields());
                throw new \DomainException(
                    "Penerbitan dokumen ditolak: Profil operasional brand '{$brandModel->name}' belum lengkap ({$missing} masih kosong). Silakan lengkapi data brand terlebih dahulu."
                );
            }

            // 5. Render binary PDF resmi
            $pdfBinary = DocumentTemplateRenderer::renderPdfBinary($template, $variables, isLiveIssuance: true);

            // 5. Publikasikan berkas fisik, catat immutable snapshot, dan sinkronkan ke Google Drive
            $generatedDoc = $this->publishingService->publishInternDocument(
                intern: $intern,
                documentType: $canonical,
                htmlContent: null,
                variableSnapshot: $variables,
                assetReferences: $assetReferences,
                customDocNumber: $docNumber,
                actor: $actor,
                pdfBinary: $pdfBinary,
                template: $template,
                isRevision: $isRevision,
                revisionVersion: $revisionVersion,
                forceRefresh: !empty($options['force_refresh'])
            );

            // 6. Sinkronisasi tabel historis/legacy untuk kompatibilitas mundur 100% tanpa mengubah riwayat
            $this->syncLegacyRecord($intern, $canonical, $docNumber, $variables, $generatedDoc, $options);

            // 7. Audit log penerbitan
            if ($actor && in_array($actor->role, DocumentGeneratorService::PRIVILEGED_ROLES, true)) {
                $actionType = $isRevision ? 'revised' : 'generated';
                $this->docGeneratorService->logDocumentAccess($canonical, $intern, $actor, $actionType);
            }

            return $generatedDoc;
        });
    }

    /**
     * Ulangi sinkronisasi cloud drive untuk GeneratedDocument secara aman dan idempotent.
     */
    public function retryDocumentSync(GeneratedDocument $doc, ?User $actor = null): array
    {
        return $this->publishingService->retrySync($doc, $actor);
    }

    /**
     * Sinkronisasikan rekam dokumen ke tabel legacy aplikasi tanpa merusak atau mengubah data historis.
     */
    protected function syncLegacyRecord(
        InternshipRegistration $intern,
        string $canonicalType,
        string $docNumber,
        array $variables,
        GeneratedDocument $generatedDoc,
        array $options = []
    ): void {
        $brand = $intern->brandRel;
        $companyName = $variables['perusahaan.nama'] ?? ($brand?->name ?? 'Seven Inc');
        $signatoryName = $variables['dokumen.penandatangan_nama'] ?? ($brand?->signatory_name ?? 'Ari Setia Husbana');
        $signatoryPosition = $variables['dokumen.penandatangan_jabatan'] ?? ($brand?->signatory_position ?? 'HRD');

        match ($canonicalType) {
            self::TYPE_LOA => tap(InternLoa::firstOrNew(['intern_id' => $intern->id]), function ($rec) use ($docNumber, $intern, $companyName, $brand, $signatoryName, $signatoryPosition) {
                if (!$rec->exists) {
                    $rec->created_at = now();
                }
                $rec->fill([
                    'loa_number'           => $docNumber,
                    'accepted_start_date'  => $intern->start_date ?? now(),
                    'accepted_end_date'    => $intern->end_date ?? now(),
                    'company_name'         => $companyName,
                    'company_logo_path'    => $brand?->logo ?? $rec->company_logo_path,
                    'signatory_name'       => $signatoryName,
                    'signatory_position'   => $signatoryPosition,
                    'signature_image_path' => $brand?->signature ?? $rec->signature_image_path,
                ])->save();
            }),
            self::TYPE_SKL => tap(SklDocument::firstOrNew(['intern_id' => $intern->id]), function ($rec) use ($docNumber, $companyName, $brand, $signatoryName, $signatoryPosition) {
                $rec->fill([
                    'skl_number'           => $docNumber,
                    'company_name'         => $companyName,
                    'company_logo_path'    => $brand?->logo ?? $rec->company_logo_path ?? null,
                    'signatory_name'       => $signatoryName,
                    'signatory_position'   => $signatoryPosition,
                    'signature_image_path' => $brand?->signature ?? $rec->signature_image_path ?? null,
                ])->save();
            }),
            self::TYPE_CERTIFICATE => tap(InternCertificate::firstOrNew(['intern_id' => $intern->id]), function ($rec) use ($docNumber, $companyName, $brand, $signatoryName, $signatoryPosition) {
                if (!$rec->exists) {
                    $rec->created_at = now();
                }
                $rec->fill([
                    'certificate_number'    => $docNumber,
                    'company_name'          => $companyName,
                    'background_image_path' => $brand?->internship_certificate_bg ?? $rec->background_image_path,
                    'company_logo_path'     => $brand?->logo ?? $rec->company_logo_path,
                    'signatory_name'        => $signatoryName,
                    'signatory_position'    => $signatoryPosition,
                    'signature_image_path'  => $brand?->signature ?? $rec->signature_image_path,
                ])->save();
            }),
            self::TYPE_ASSESSMENT => tap(InternAssessment::firstOrNew(['intern_id' => $intern->id]), function ($rec) use ($docNumber, $companyName, $brand, $signatoryName, $signatoryPosition, $variables, $options) {
                $rec->fill([
                    'assessment_number'    => $docNumber,
                    'aspek_penilaian'      => $options['aspek_penilaian'] ?? $rec->aspek_penilaian ?? [],
                    'rata_rata'            => (float) ($variables['penilaian.nilai_akhir'] ?? $options['rata_rata'] ?? $rec->rata_rata ?? 0),
                    'company_name'         => $companyName,
                    'company_address'      => $variables['perusahaan.alamat'] ?? ($rec->company_address ?: ''),
                    'company_logo_path'    => $brand?->logo ?? $rec->company_logo_path,
                    'signatory_name'       => $signatoryName,
                    'signatory_position'   => $signatoryPosition,
                    'signature_image_path' => $brand?->signature ?? $rec->signature_image_path,
                ])->save();
            }),
            self::TYPE_RECOMMENDATION => tap(InternExtra::firstOrNew(['intern_id' => $intern->id]), function ($rec) use ($docNumber, $companyName, $brand, $signatoryName, $signatoryPosition, $variables, $generatedDoc, $intern) {
                $rec->fill([
                    'rekomendasi_path'       => $generatedDoc->file_path,
                    'rekomendasi_url'        => $generatedDoc->drive_web_view_link,
                    'rekomendasi_granted_at' => $rec->rekomendasi_granted_at ?: now(),
                    'letter_number'          => $docNumber,
                    'brand_id'               => $intern->brand_id,
                    'company_name'           => $companyName,
                    'company_address'        => $variables['perusahaan.alamat'] ?? ($rec->company_address ?: ''),
                    'company_logo_path'      => $brand?->logo ?? $rec->company_logo_path,
                    'signatory_name'         => $signatoryName,
                    'signatory_position'     => $signatoryPosition,
                    'signature_image_path'   => $brand?->signature ?? $rec->signature_image_path,
                ])->save();
            }),
            default => null,
        };
    }

    /**
     * Generate format nomor unik sertifikat pemagang.
     */
    public function generateUniqueCertificateNumber(InternshipRegistration $intern): string
    {
        $roman = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        $month = (int) date('n');
        $romanMonth = $roman[$month];
        $year = date('Y');

        $brandModel = $intern->brandRel ?? ($intern->brand_id ? \App\Models\Brand::find($intern->brand_id) : null);
        $brandStr = strtoupper($brandModel?->code ?? 'SI');
        $divisionCode = $intern->division?->code ?? 'ADM';

        $last = InternCertificate::where('certificate_number', 'LIKE', "%/SERT/%/{$romanMonth}/{$year}")
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($last && preg_match('/^(\d{3})\/SERT\//', $last->certificate_number, $m)) {
            $seq = (int) $m[1] + 1;
        }
        $seqStr = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        return "{$seqStr}/SERT/{$divisionCode}/SEVEN.{$brandStr}/{$romanMonth}/{$year}";
    }

    /**
     * Generate format nomor unik surat penilaian pemagang.
     */
    public function generateUniqueAssessmentNumber(InternshipRegistration $intern): string
    {
        $roman = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        $month = (int) date('n');
        $romanMonth = $roman[$month];
        $year = date('Y');

        $brandModel = $intern->brandRel ?? ($intern->brand_id ? \App\Models\Brand::find($intern->brand_id) : null);
        $brandStr = strtoupper($brandModel?->code ?? 'SI');
        $divisionCode = $intern->division?->code ?? 'UMUM';

        $last = InternAssessment::where('assessment_number', 'LIKE', "%/PEN/%/{$romanMonth}/{$year}")
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($last && preg_match('/^(\d{3})\/PEN\//', $last->assessment_number, $m)) {
            $seq = (int) $m[1] + 1;
        }
        $seqStr = str_pad((string) $seq, 3, '0', STR_PAD_LEFT);

        return "{$seqStr}/PEN/{$divisionCode}/SEVEN.{$brandStr}/{$romanMonth}/{$year}";
    }

    /**
     * Generate format nomor unik surat rekomendasi pemagang.
     */
    public function generateUniqueRecommendationNumber(InternshipRegistration $intern): string
    {
        $roman = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        $month = (int) date('n');
        $romanMonth = $roman[$month];
        $year = date('Y');

        $brandModel = $intern->brandRel ?? ($intern->brand_id ? \App\Models\Brand::find($intern->brand_id) : null);
        $brandStr = strtoupper($brandModel?->code ?? 'SI');
        $divisionCode = $intern->division?->code ?? 'UMUM';

        $running = str_pad((string) $intern->id, 3, '0', STR_PAD_LEFT);
        return "{$running}/SR/{$divisionCode}/SEVEN.{$brandStr}/{$romanMonth}/{$year}";
    }

    protected function getDefaultRecommendationBody(
        InternshipRegistration $intern,
        string $companyName,
        string $duration
    ): string {
        $startDateStr = $intern->start_date ? Carbon::parse($intern->start_date)->locale('id')->translatedFormat('d F Y') : 'awal periode';
        $endDateStr   = $intern->end_date ? Carbon::parse($intern->end_date)->locale('id')->translatedFormat('d F Y') : 'akhir periode';
        $name = $intern->fullname ?? 'pemagang';
        return "Yang bersangkutan telah melaksanakan magang di {$companyName} sejak {$startDateStr} sampai dengan {$endDateStr}. Selama masa magang, saudara/i {$name} menunjukkan performa luar biasa, kemampuan komunikasi yang efektif, serta komitmen tinggi dalam menyelesaikan setiap proyek yang diberikan.";
    }
}
