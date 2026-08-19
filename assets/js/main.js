/**
 * ============================================================
 * main.js — PT Delta Ocean Shipping
 * Menggunakan jQuery untuk manipulasi DOM & Axios untuk
 * Asynchronous request, sesuai Tech Stack pada SKPL.
 * ============================================================
 */
window.DeltaOcean = (function () {

    /* ------------------------------------------------------------
     * 1. POPUP NOTIFIKASI (Alur Pop-up — Activity Diagram C)
     * ------------------------------------------------------------ */
    function renderCriticalModal(items) {
        $('#do-critical-modal').remove();

        const rows = items.map(function (it) {
            const cls = it.status_kode === 'expired' ? 'badge badge--danger' : 'badge badge--warning';
            return `
                <li class="critical-item">
                    <div>
                        <div class="critical-item__name">${escapeHtml(it.nama_sertifikat)}</div>
                        <div class="critical-item__meta">${escapeHtml(it.nama_kapal)} &middot; ${escapeHtml(it.tanggal_expired)}</div>
                    </div>
                    <span class="${cls}">${escapeHtml(it.status_label)}</span>
                </li>`;
        }).join('');

        const modal = $(`
            <div id="do-critical-modal" class="modal-overlay">
                <div class="modal-content modal-content--critical">
                    <div class="modal-content__head">
                        <h3>&#9888; Peringatan Dokumen Kedaluwarsa</h3>
                        <button type="button" class="modal-close" id="do-critical-close">&times;</button>
                    </div>
                    <p style="padding:16px 24px 4px;color:#4A5C6B;font-size:13.5px;">
                        Ditemukan <strong>${items.length}</strong> dokumen yang sudah/hampir kedaluwarsa (H-7). Mohon segera ditindaklanjuti.
                    </p>
                    <ul class="critical-list">${rows}</ul>
                    <div class="critical-footer">
                        <button type="button" id="do-critical-ack" class="btn btn--primary btn--block">Tutup &amp; Lanjutkan</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);
        $('#do-critical-close, #do-critical-ack').on('click', function () {
            $('#do-critical-modal').remove();
        });
    }

    function checkExpiryPopup(showPopupIfAny) {
        axios.get('api/check_expiry.php')
            .then(function (res) {
                const { total, data } = res.data;
                const $badge = $('#bell-badge');
                if (total > 0) {
                    $badge.text(total).removeAttr('hidden');
                } else {
                    $badge.attr('hidden', true);
                }
                if (showPopupIfAny && total > 0) {
                    renderCriticalModal(data);
                }
                $('#btn-bell').off('click').on('click', function () {
                    if (total > 0) renderCriticalModal(data);
                    else {
                        Swal.fire({ title: 'Tidak ada peringatan', text: 'Semua dokumen dalam status aman.', icon: 'success' });
                    }
                });
            })
            .catch(function (err) {
                // Jangan didiamkan total — catat ke console supaya mudah didiagnosis
                // kalau API-nya gagal (mis. sesi habis, error PHP, dsb).
                console.error('checkExpiryPopup gagal memuat api/check_expiry.php:', err);
            });
    }

    /* ------------------------------------------------------------
     * 2. HALAMAN MANAJEMEN DOKUMEN
     * ------------------------------------------------------------ */
    let state = { page: 1, search: '', id_kapal: null, role: 'staf_kapal' };
    let searchTimer = null;

    /* File / folder yang dipilih user (array of File), lengkap dengan
     * status "dicentang" (mau diunggah atau tidak) per file. */
    let selectedFiles = []; // { file: File, checked: boolean }

    function statusExtra(kode) {
        // memberi warna angka H- pada badge warning jika mendesak
        return kode;
    }

    function renderTableRows(rows, role) {
        if (!rows.length) {
            const colspan = role === 'admin_induk' ? 6 : 5;
            $('#table-body').html(`<tr><td colspan="${colspan}" class="empty-cell">Belum ada dokumen untuk kapal ini.</td></tr>`);
            return;
        }

        const html = rows.map(function (r) {
            const kapalCell = role === 'admin_induk' ? `<td>${escapeHtml(r.nama_kapal)}</td>` : '';
            return `
                <tr>
                    <td><strong>${escapeHtml(r.nama_sertifikat)}</strong></td>
                    ${kapalCell}
                    <td>${escapeHtml(r.tanggal_display)}</td>
                    <td><span class="${r.status_class}">${escapeHtml(r.status_label)}</span></td>
                    <td><a class="link-file" href="${escapeHtml(r.file_path)}" target="_blank" rel="noopener">Lihat File</a></td>
                    <td class="col-actions">
                        <div class="row-actions">
                            <button class="btn btn--sm btn--ghost" data-action="edit"
                                data-id="${r.id_dokumen}"
                                data-nama="${escapeHtml(r.nama_sertifikat)}"
                                data-tanggal="${r.tanggal_expired}"
                                data-kapal="${state.id_kapal}">Edit</button>
                            <button class="btn btn--sm btn--danger-outline" data-action="delete" data-id="${r.id_dokumen}">Hapus</button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        $('#table-body').html(html);
    }

    function renderPagination(total, page, perPage) {
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        $('#pagination-summary').text(`Menampilkan halaman ${page} dari ${totalPages} (${total} dokumen)`);

        let html = '';
        html += `<button ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">&lsaquo;</button>`;
        for (let p = 1; p <= totalPages; p++) {
            html += `<button class="${p === page ? 'active' : ''}" data-page="${p}">${p}</button>`;
        }
        html += `<button ${page >= totalPages ? 'disabled' : ''} data-page="${page + 1}">&rsaquo;</button>`;
        $('#pagination-controls').html(html);
    }

    function loadDokumen() {
        if (!state.id_kapal) {
            $('#table-body').html('<tr><td colspan="6" class="empty-cell">Belum ada kapal terdaftar.</td></tr>');
            return;
        }
        $('#table-body').html('<tr><td colspan="6" class="empty-cell">Memuat data...</td></tr>');

        axios.get('dokumen_action.php', {
            params: {
                action: 'list',
                id_kapal: state.id_kapal,
                search: state.search,
                page: state.page,
            }
        }).then(function (res) {
            const d = res.data;
            if (!d.success) {
                Swal.fire('Gagal', d.message || 'Terjadi kesalahan.', 'error');
                return;
            }
            renderTableRows(d.data, state.role);
            renderPagination(d.total, d.page, d.per_page);
            $('#quota-info').text(`${d.quota} / ${d.quota_max} dokumen`);
        }).catch(function () {
            $('#table-body').html('<tr><td colspan="6" class="empty-cell">Gagal memuat data. Coba muat ulang halaman.</td></tr>');
        });
    }

    function openModal(mode, data) {
        $('#form-dokumen')[0].reset();
        $('#f-id_dokumen').val('');
        $('#f-file-preview').attr('hidden', true);
        $('#f-file-preview-list').empty();

        if (mode === 'edit' && data) {
            $('#modal-title').text('Edit Dokumen');
            $('#f-id_dokumen').val(data.id);
            $('#f-id_kapal').val(data.kapal);
            $('#f-nama_sertifikat').val(data.nama);
            $('#f-tanggal_expired').val(data.tanggal).attr('required', true);
            $('#f-tanggal_label').html('Tanggal Kedaluwarsa');
            $('#f-file-hint').text('— kosongkan jika tidak ingin mengganti file');
        } else {
            $('#modal-title').text('Tambah Dokumen');
            $('#f-id_kapal').val(state.id_kapal);
            $('#f-tanggal_expired').removeAttr('required');
            $('#f-tanggal_label').html('Tanggal Kedaluwarsa <small>(cadangan — dipakai jika tanggal tidak terdeteksi dari nama file)</small>');
            $('#f-file-hint').html('— wajib untuk dokumen baru. Saat memilih folder, sistem membaca isinya lalu menampilkan daftar file — centang hanya file yang ingin diunggah. Tanggal kedaluwarsa otomatis dibaca dari nama tiap file (mis. <code>SIUP_2026-08-15.pdf</code>, <code>Sertifikat_15-08-2026.pdf</code>, atau <code>Sertifikat_15082026.pdf</code>, atau tahun 2 digit seperti <code>SKAT_27-07-27.pdf</code>).');
        }
        selectedFiles = [];
        $('#f-file_dokumen').val('');
        $('#f-file_dokumen-single').val('');
        $('#modal-dokumen').removeAttr('hidden');
    }

    /* ------------------------------------------------------------
     * 2b. AUTO-DETEKSI TANGGAL DARI NAMA FILE (preview di sisi klien)
     * Mencerminkan logika ekstrakTanggalDariNamaFile() di PHP,
     * hanya untuk memberi pratinjau instan sebelum file diunggah.
     * ------------------------------------------------------------ */
    const BULAN_MAP = {
        jan: 1, januari: 1, january: 1, feb: 2, februari: 2, february: 2,
        mar: 3, maret: 3, march: 3, apr: 4, april: 4, mei: 5, may: 5,
        jun: 6, juni: 6, june: 6, jul: 7, juli: 7, july: 7,
        agu: 8, agt: 8, agustus: 8, aug: 8, august: 8,
        sep: 9, sept: 9, september: 9, okt: 10, oktober: 10, oct: 10, october: 10,
        nov: 11, november: 11, des: 12, desember: 12, dec: 12, december: 12,
    };

    function validasiTanggalJS(y, mo, d) {
        const dt = new Date(y, mo - 1, d);
        if (dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d) {
            return `${String(y).padStart(4, '0')}-${String(mo).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        }
        return null;
    }

    function deteksiTanggalDariNamaFile(fileName) {
        const nama = fileName.replace(/\.[^/.]+$/, ''); // buang ekstensi
        let m;

        if ((m = nama.match(/(20\d{2})[-_.](0[1-9]|1[0-2])[-_.](0[1-9]|[12]\d|3[01])(?!\d)/))) {
            const r = validasiTanggalJS(+m[1], +m[2], +m[3]);
            if (r) return r;
        }
        if ((m = nama.match(/(?<!\d)(0[1-9]|[12]\d|3[01])[-_.](0[1-9]|1[0-2])[-_.](20\d{2})/))) {
            const r = validasiTanggalJS(+m[3], +m[2], +m[1]);
            if (r) return r;
        }
        if ((m = nama.match(/(?<!\d)(0[1-9]|[12]\d|3[01])(0[1-9]|1[0-2])(20\d{2})(?!\d)/))) {
            const r = validasiTanggalJS(+m[3], +m[2], +m[1]);
            if (r) return r;
        }
        if ((m = nama.match(/(?<!\d)(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])(?!\d)/))) {
            const r = validasiTanggalJS(+m[1], +m[2], +m[3]);
            if (r) return r;
        }
        if ((m = nama.match(/(?<!\d)(0?[1-9]|[12]\d|3[01])[\s_-]+([A-Za-z]{3,9})[\s_-]+(20\d{2})/))) {
            const key = m[2].toLowerCase();
            if (BULAN_MAP[key]) {
                const r = validasiTanggalJS(+m[3], BULAN_MAP[key], +m[1]);
                if (r) return r;
            }
        }
        // Pola 6: DD-MM-YY — tahun 2 digit (mis. "27-07-27" = 27 Juli 2027), dicek paling
        // terakhir supaya tidak salah menangkap sebagian dari format tahun 4 digit di atas.
        if ((m = nama.match(/(?<!\d)(0[1-9]|[12]\d|3[01])[-_.](0[1-9]|1[0-2])[-_.](\d{2})(?!\d)/))) {
            const r = validasiTanggalJS(2000 + (+m[3]), +m[2], +m[1]);
            if (r) return r;
        }
        return null;
    }

    function tambahFileKeSeleksi(fileList) {
        // Tambahkan file baru ke daftar (hindari duplikat nama+ukuran persis sama)
        Array.from(fileList || []).forEach(function (f) {
            const sudahAda = selectedFiles.some(function (sf) {
                return sf.file.name === f.name && sf.file.size === f.size && (sf.file.webkitRelativePath || '') === (f.webkitRelativePath || '');
            });
            if (!sudahAda) {
                selectedFiles.push({ file: f, checked: true });
            }
        });
        renderFilePreview();
    }

    function hitungTerpilih() {
        return selectedFiles.filter(function (sf) { return sf.checked; }).length;
    }

    function renderFilePreview() {
        const $box = $('#f-file-preview');
        if (!selectedFiles.length) {
            $box.attr('hidden', true);
            $('#f-file-preview-list').empty();
            return;
        }

        const rows = selectedFiles.map(function (sf, idx) {
            const tgl = deteksiTanggalDariNamaFile(sf.file.name);
            const chip = tgl
                ? `<span class="badge badge--ok">${tgl}</span>`
                : `<span class="badge badge--warning">tanggal tidak terdeteksi</span>`;
            const namaTampil = sf.file.webkitRelativePath || sf.file.name;
            return `
                <li>
                    <label style="display:flex; align-items:center; gap:8px; width:100%; cursor:pointer;">
                        <input type="checkbox" class="chk-file-item" data-idx="${idx}" ${sf.checked ? 'checked' : ''}>
                        <span class="file-preview__name" style="flex:1;">${escapeHtml(namaTampil)}</span>
                        ${chip}
                    </label>
                </li>`;
        }).join('');

        $('#f-file-preview-list').html(rows);
        $('#f-file-preview-title').text(`${hitungTerpilih()} dari ${selectedFiles.length} file akan diunggah:`);
        $box.removeAttr('hidden');
    }

    function closeModal() {
        $('#modal-dokumen').attr('hidden', true);
        selectedFiles = [];
        renderFilePreview();
    }

    function initDokumenPage(role) {
        state.role = role;
        state.id_kapal = parseInt($('#filter-kapal').val(), 10) || null;

        loadDokumen();

        $('#filter-kapal').on('change', function () {
            state.id_kapal = parseInt($(this).val(), 10) || null;
            state.page = 1;
            loadDokumen();
        });

        $('#search-box').on('input', function () {
            const val = $(this).val();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                state.search = val;
                state.page = 1;
                loadDokumen();
            }, 350);
        });

        $('#pagination-controls').on('click', 'button[data-page]', function () {
            const p = parseInt($(this).data('page'), 10);
            if (!p || $(this).is(':disabled')) return;
            state.page = p;
            loadDokumen();
        });

        $('#btn-tambah').on('click', function () { openModal('add'); });
        $('#modal-close, #btn-batal').on('click', closeModal);

        // Tombol "Pilih Folder" -> memicu input webkitdirectory (menyertakan isi sub-folder)
        $('#btn-pilih-folder').on('click', function () { $('#f-file_dokumen').trigger('click'); });
        // Tombol "Pilih File" -> memicu input multi-file biasa (tanpa folder)
        $('#btn-pilih-file').on('click', function () { $('#f-file_dokumen-single').trigger('click'); });

        $('#f-file_dokumen').on('change', function () {
            tambahFileKeSeleksi(this.files);
            this.value = ''; // reset supaya bisa pilih folder yang sama lagi jika perlu
        });
        $('#f-file_dokumen-single').on('change', function () {
            tambahFileKeSeleksi(this.files);
            this.value = '';
        });

        $('#f-file-preview').on('change', '.chk-file-item', function () {
            const idx = parseInt($(this).data('idx'), 10);
            if (selectedFiles[idx]) selectedFiles[idx].checked = $(this).is(':checked');
            $('#f-file-preview-title').text(`${hitungTerpilih()} dari ${selectedFiles.length} file akan diunggah:`);
        });

        $('#btn-pilih-semua-file').on('click', function () {
            selectedFiles.forEach(function (sf) { sf.checked = true; });
            renderFilePreview();
        });
        $('#btn-batal-semua-file').on('click', function () {
            selectedFiles.forEach(function (sf) { sf.checked = false; });
            renderFilePreview();
        });

        $('#table-body').on('click', 'button[data-action="edit"]', function () {
            openModal('edit', {
                id: $(this).data('id'),
                nama: $(this).data('nama'),
                tanggal: $(this).data('tanggal'),
                kapal: $(this).data('kapal'),
            });
        });

        $('#table-body').on('click', 'button[data-action="delete"]', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Hapus dokumen ini?',
                text: 'Tindakan ini tidak dapat dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#B7392C',
            }).then(function (result) {
                if (!result.isConfirmed) return;
                const fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id_dokumen', id);
                axios.post('dokumen_action.php', fd).then(function (res) {
                    if (res.data.success) {
                        Swal.fire({ title: 'Terhapus', text: res.data.message, icon: 'success', timer: 1400, showConfirmButton: false });
                        loadDokumen();
                    } else {
                        Swal.fire('Gagal', res.data.message, 'error');
                    }
                });
            });
        });

        $('#form-dokumen').on('submit', function (e) {
            e.preventDefault();

            const isEdit = !!$('#f-id_dokumen').val();
            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('id_dokumen', $('#f-id_dokumen').val());
            fd.append('id_kapal', $('#f-id_kapal').val());
            fd.append('nama_sertifikat', $('#f-nama_sertifikat').val());
            fd.append('tanggal_expired', $('#f-tanggal_expired').val());

            const fileTerpilih = selectedFiles.filter(function (sf) { return sf.checked; });

            if (!isEdit && fileTerpilih.length === 0) {
                Swal.fire('Belum ada file dipilih', 'Pilih folder atau file terlebih dahulu, lalu centang minimal satu file untuk diunggah.', 'warning');
                return;
            }

            fileTerpilih.forEach(function (sf) {
                fd.append('file_dokumen[]', sf.file, sf.file.name);
            });

            axios.post('dokumen_action.php', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
                .then(function (res) {
                    if (res.data.success) {
                        closeModal();
                        const gagalTanggal = res.data.gagal_tanggal || [];
                        if (gagalTanggal.length) {
                            // Ada file yang dilewati karena tanggal tidak terdeteksi dan tidak ada cadangan
                            Swal.fire({
                                title: 'Sebagian dokumen tersimpan',
                                icon: 'warning',
                                html: `<p style="text-align:left;margin:0 0 8px;">${res.data.message}</p>
                                       <p style="text-align:left;font-size:13px;color:#4A5C6B;margin:0 0 4px;"><strong>File dilewati:</strong></p>
                                       <ul style="text-align:left;font-size:13px;max-height:160px;overflow:auto;">${gagalTanggal.map(f => `<li>${escapeHtml(f)}</li>`).join('')}</ul>`,
                            });
                        } else {
                            Swal.fire({ title: 'Berhasil', text: res.data.message, icon: 'success', timer: 2000, showConfirmButton: false });
                        }
                        loadDokumen();
                    } else {
                        Swal.fire('Gagal', res.data.message, 'error');
                    }
                })
                .catch(function () {
                    Swal.fire('Gagal', 'Terjadi kesalahan pada server.', 'error');
                });
        });
    }

    /* ------------------------------------------------------------ */
    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, function (m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
        });
    }

    return { checkExpiryPopup, initDokumenPage };
})();

/**
 * ============================================================
 * AUTO-INIT NOTIFIKASI H-7 — berlaku di SEMUA halaman
 * ------------------------------------------------------------
 * Sebelumnya checkExpiryPopup() hanya dipanggil manual dari
 * dashboard.php, sehingga badge lonceng & popup tidak pernah
 * muncul di halaman lain (Manajemen Dokumen, Master Kapal, dst).
 * Sekarang: badge diperbarui otomatis di halaman manapun yang
 * memiliki ikon lonceng (#btn-bell). Popup H-7 otomatis muncul
 * SEKALI per sesi login (memakai sessionStorage), di halaman
 * apapun yang pertama kali dibuka setelah login — tidak berulang
 * setiap pindah halaman supaya tidak mengganggu.
 * ============================================================
 */
$(function () {
    if (!$('#btn-bell').length) return; // halaman ini tidak punya ikon lonceng (mis. login.php)

    const sudahTampilKey = 'simas_h7_popup_shown';
    const tampilkanPopup = !sessionStorage.getItem(sudahTampilKey);

    DeltaOcean.checkExpiryPopup(tampilkanPopup);

    if (tampilkanPopup) {
        sessionStorage.setItem(sudahTampilKey, '1');
    }
});
