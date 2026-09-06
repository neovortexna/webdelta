<?php
/**
 * ============================================================
 * xlsx_writer.php — Penulis file .xlsx minimalis (dengan styling)
 * PT Delta Ocean Shipping
 * ------------------------------------------------------------
 * Membuat file Excel (.xlsx) asli tanpa dependensi Composer /
 * PhpSpreadsheet, hanya memakai ekstensi ZipArchive bawaan PHP.
 * Mendukung: header berwarna + tebal, border sel, lebar kolom
 * otomatis, filter otomatis, freeze header, warna baris selang-
 * seling (zebra), pewarnaan sel STATUS sesuai kondisi, dan kolom
 * yang dipaksa bertipe TEKS (mis. kolom tanggal) supaya Excel
 * tidak mengonversinya jadi tanggal/angka (penyebab tampilan
 * "########" saat kolom sempit).
 * ============================================================
 */

/** Ubah indeks kolom (0-based) menjadi huruf kolom Excel (A, B, ..., Z, AA, AB, ...) */
function xlsxKolomHuruf(int $index): string
{
    $letters = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = intdiv($index - 1, 26);
    }
    return $letters;
}

/** Escape nilai teks untuk XML */
function xlsxEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Bangun XML <row> untuk satu baris data.
 *
 * @param int   $rowNumber     Nomor baris Excel (1-based)
 * @param array $values        Nilai per kolom, berurutan
 * @param int   $styleIndex    Index cellXfs (style) default untuk baris ini
 * @param array $textColumns   Index kolom (0-based) yang WAJIB ditulis sebagai teks
 *                              murni (tidak boleh dikonversi jadi angka/tanggal oleh Excel)
 * @param array $columnStyles  Peta [index kolom => index style] untuk override style per kolom
 *                              (dipakai mis. untuk mewarnai sel STATUS)
 */
function xlsxBuatBarisXml(int $rowNumber, array $values, int $styleIndex = 0, array $textColumns = [], array $columnStyles = []): string
{
    $cells = '';
    foreach (array_values($values) as $i => $val) {
        $ref   = xlsxKolomHuruf($i) . $rowNumber;
        $style = $columnStyles[$i] ?? $styleIndex;
        $paksaTeks = in_array($i, $textColumns, true);

        if ($val === null || $val === '') {
            $cells .= '<c r="' . $ref . '" s="' . $style . '"/>';
        } elseif (!$paksaTeks && is_numeric($val) && !preg_match('/^0[0-9]/', (string) $val)) {
            // Angka murni dituliskan sebagai number cell (kecuali diawali 0, mis. no HP,
            // atau kolom yang secara eksplisit dipaksa teks, mis. kolom tanggal)
            $cells .= '<c r="' . $ref . '" s="' . $style . '"><v>' . xlsxEscape((string) $val) . '</v></c>';
        } else {
            $cells .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . xlsxEscape((string) $val) . '</t></is></c>';
        }
    }
    return '<row r="' . $rowNumber . '">' . $cells . '</row>';
}

/** Hitung panjang string dengan aman, walau ekstensi mbstring tidak tersedia di server */
function xlsxPanjangString(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Hitung lebar kolom otomatis (karakter) berdasarkan isi terpanjang, dengan batas wajar */
function xlsxHitungLebarKolom(array $headers, array $rows): array
{
    $lebar = [];
    foreach ($headers as $i => $h) {
        $lebar[$i] = xlsxPanjangString((string) $h) + 2;
    }
    foreach ($rows as $row) {
        foreach (array_values($row) as $i => $val) {
            $len = xlsxPanjangString((string) $val) + 2;
            if (!isset($lebar[$i]) || $len > $lebar[$i]) {
                $lebar[$i] = $len;
            }
        }
    }
    foreach ($lebar as $i => $w) {
        $lebar[$i] = max(10, min(45, $w)); // batas minimum 10, maksimum 45 karakter
    }
    return $lebar;
}

/**
 * Buat file .xlsx dari header + baris data, lalu langsung stream sebagai
 * download ke browser (memanggil header() & exit di dalamnya).
 *
 * @param string $filename    Nama file unduhan, mis. "hasil_pencarian_surat.xlsx"
 * @param array  $headers     Daftar judul kolom, mis. ['No', 'Nama Kapal', ...]
 * @param array  $rows        Array asosiatif/berindeks per baris, urutan harus sama dengan $headers
 * @param string $sheetName   Nama sheet (default "Sheet1")
 * @param array  $options     Opsi tambahan untuk mempercantik & mengamankan tampilan:
 *                             - 'textColumns' (array int)  index kolom yang dipaksa teks murni
 *                               (dipakai untuk kolom tanggal supaya tidak tampil "########")
 *                             - 'statusColumn' (int|null)  index kolom STATUS untuk diwarnai otomatis
 *                             - 'statusCodes' (array)      kode status per baris, sejajar dengan $rows,
 *                               nilai: 'ok' (hijau) | 'warning' (kuning) | 'expired' (merah)
 */
function streamXlsxDownload(string $filename, array $headers, array $rows, string $sheetName = 'Sheet1', array $options = []): void
{
    $textColumns  = $options['textColumns'] ?? [];
    $statusColumn = $options['statusColumn'] ?? null;
    $statusCodes  = $options['statusCodes'] ?? [];

    if (!class_exists('ZipArchive')) {
        // Fallback: jika ekstensi ZipArchive tidak tersedia di server, kirim sebagai CSV
        // agar fitur export tetap berfungsi (Excel tetap bisa membuka file CSV).
        streamCsvDownload(preg_replace('/\.xlsx$/', '.csv', $filename), $headers, $rows, $textColumns);
        return;
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmpFile === false) {
        http_response_code(500);
        die('Gagal membuat file sementara untuk export Excel.');
    }

    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);

    $zip->addEmptyDir('_rels');
    $zip->addEmptyDir('xl');
    $zip->addEmptyDir('xl/_rels');
    $zip->addEmptyDir('xl/worksheets');

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
        '<Default Extension="xml" ContentType="application/xml"/>' .
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
        '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
        '</Types>'
    );

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
        '</Relationships>'
    );

    $safeSheetName = xlsxEscape(substr($sheetName, 0, 31));
    $colCount = max(1, count($headers));
    $lastCol  = xlsxKolomHuruf($colCount - 1);
    $lastRowPlaceholder = 1 + count($rows);
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
        '<sheets><sheet name="' . $safeSheetName . '" sheetId="1" r:id="rId1"/></sheets>' .
        '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">' . $safeSheetName . '!$A$1:$' . $lastCol . '$' . $lastRowPlaceholder . '</definedName></definedNames>' .
        '</workbook>'
    );

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
        '</Relationships>'
    );

    // ---------------------------------------------------------
    // styles.xml — palet warna sederhana:
    //   0 = default, 1 = header (biru tua, teks putih, tebal, border)
    //   2 = sel data genap (putih, border), 3 = sel data ganjil (abu muda, border)
    //   4 = status OK (hijau muda), 5 = status WARNING (kuning muda), 6 = status EXPIRED (merah muda)
    // ---------------------------------------------------------
    $zip->addFromString('xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<fonts count="3">' .
            '<font><sz val="10.5"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="10.5"/><name val="Calibri"/></font>' .
        '</fonts>' .
        '<fills count="7">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF1A3C5E"/><bgColor indexed="64"/></patternFill></fill>' . // 2 header navy
            '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F6FA"/><bgColor indexed="64"/></patternFill></fill>' . // 3 zebra abu muda
            '<fill><patternFill patternType="solid"><fgColor rgb="FFDFF5E1"/><bgColor indexed="64"/></patternFill></fill>' . // 4 hijau (Berlaku)
            '<fill><patternFill patternType="solid"><fgColor rgb="FFFDE8D0"/><bgColor indexed="64"/></patternFill></fill>' . // 5 kuning (H-warning)
            '<fill><patternFill patternType="solid"><fgColor rgb="FFFBDAD8"/><bgColor indexed="64"/></patternFill></fill>' . // 6 merah (Kedaluwarsa)
        '</fills>' .
        '<borders count="2">' .
            '<border><left/><right/><top/><bottom/><diagonal/></border>' .
            '<border><left style="thin"><color rgb="FFCCCCCC"/></left><right style="thin"><color rgb="FFCCCCCC"/></right><top style="thin"><color rgb="FFCCCCCC"/></top><bottom style="thin"><color rgb="FFCCCCCC"/></bottom><diagonal/></border>' .
        '</borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="7">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' . // 0 default
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . // 1 header
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' . // 2 data genap
            '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' . // 3 data ganjil (zebra)
            '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 4 status ok (hijau)
            '<xf numFmtId="0" fontId="2" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 5 status warning (kuning)
            '<xf numFmtId="0" fontId="2" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' . // 6 status expired (merah)
        '</cellXfs>' .
        '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>' .
        '</styleSheet>'
    );

    $rowsXml = xlsxBuatBarisXml(1, $headers, 1); // baris header, style 1 untuk semua kolom

    $r = 2;
    foreach ($rows as $idx => $row) {
        $zebra = ($idx % 2 === 0) ? 2 : 3; // genap/ganjil
        $columnStyles = [];
        foreach ($textColumns as $tc) {
            $columnStyles[$tc] = $zebra; // kolom teks tetap pakai style zebra biasa (border + wrap), nilainya dipaksa string
        }
        if ($statusColumn !== null) {
            $kode = $statusCodes[$idx] ?? null;
            if ($kode === 'ok') {
                $columnStyles[$statusColumn] = 4;
            } elseif ($kode === 'warning') {
                $columnStyles[$statusColumn] = 5;
            } elseif ($kode === 'expired') {
                $columnStyles[$statusColumn] = 6;
            }
        }
        $rowsXml .= xlsxBuatBarisXml($r, $row, $zebra, $textColumns, $columnStyles);
        $r++;
    }

    $lastRow  = max(1, $r - 1);

    $lebarKolom = xlsxHitungLebarKolom($headers, $rows);
    $colsXml = '<cols>';
    foreach ($lebarKolom as $i => $w) {
        $colNum = $i + 1;
        $colsXml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $zip->addFromString('xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
        '<dimension ref="A1:' . $lastCol . $lastRow . '"/>' .
        '<sheetViews><sheetView tabSelected="1" workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
        $colsXml .
        '<sheetData>' . $rowsXml . '</sheetData>' .
        '<autoFilter ref="A1:' . $lastCol . '1"/>' .
        '</worksheet>'
    );

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    readfile($tmpFile);
    unlink($tmpFile);
    exit;
}

/**
 * Fallback CSV (dipakai jika ZipArchive tidak tersedia di server).
 * Kolom yang ada di $textColumns akan dibungkus formula ="..." supaya
 * saat CSV dibuka di Excel, isinya TIDAK otomatis dikonversi jadi
 * tanggal/angka (yang menyebabkan tampilan "########" di kolom sempit).
 */
function streamCsvDownload(string $filename, array $headers, array $rows, array $textColumns = []): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM agar karakter Excel terbaca benar
    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $row = array_values($row);
        foreach ($textColumns as $tc) {
            if (isset($row[$tc]) && $row[$tc] !== '') {
                // Bungkus sebagai formula teks literal agar Excel tidak menebak tipe data
                $row[$tc] = '="' . str_replace('"', '""', (string) $row[$tc]) . '"';
            }
        }
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
