(function () {
    'use strict';
    function text(tag, value, cls) {
        const node = document.createElement(tag); node.textContent = value;
        if (cls) node.className = cls;
        return node;
    }
    function quantity(stock, unit) {
        return !stock || stock.qty === null ? 'Belum diketahui' : Number(stock.qty).toLocaleString('id-ID', {maximumFractionDigits:4}) + ' ' + unit;
    }
    function paint(data) {
        const byLine = new Map((data ? data.rows : []).map(row => [Number(row.line), row]));
        document.querySelectorAll('[data-live-stock-line]').forEach(cell => {
            cell.replaceChildren();
            const row = byLine.get(Number(cell.dataset.liveStockLine));
            if (!row) { cell.append(text('small', data ? 'Tidak terkait bahan baku' : 'Menunggu cek stok…', 'text-muted')); return; }
            cell.style.minWidth = '180px'; cell.style.whiteSpace = 'normal';
            if (!data.warehouse_only) cell.append(text('div', 'Divisi: ' + quantity(row.division,row.uom), 'fw-semibold'));
            cell.append(text('div', 'Gudang: ' + quantity(row.warehouse,row.uom), 'fw-semibold'));
            if (row.warning) { const warning=text('div',row.warning,'small fw-semibold'); warning.style.color='#8a4800'; cell.append(warning); }
            cell.append(text('small', 'Dibaca ' + data.checked_at, 'text-muted'));
        });
    }
    // Shared renderer also used by the signed division-verification client.
    window.ProcurementCurrentStock = {paint:paint, create:function (options) {
        let generation = 0, timer, pending, data = null, last = '';
        const status = document.getElementById('manualStockStatus');
        const refresh = document.getElementById('manualStockRefresh');
        function message(value) { if (status) status.textContent = value; }
        async function load() {
            clearTimeout(timer);
            const mine = ++generation;
            if (pending) pending.abort();
            const controller = new AbortController(); pending = controller;
            data = null; paint(null);
            const payload = options.payload(); last = JSON.stringify(payload);
            if (!payload.lines.length) { message('Tambahkan barang untuk memeriksa stok.'); paint({rows:[]}); return null; }
            message('Memeriksa stok sistem divisi / gudang…');
            const deadline = setTimeout(() => controller.abort(),12000);
            try {
                // The reader is bounded per request, not a new limit on the size of a manual PO/SR.
                let merged = null;
                for (let offset=0; offset<payload.lines.length; offset+=100) {
                    const response = await fetch(options.url,{method:'POST',credentials:'same-origin',signal:controller.signal,
                        headers:{'Content-Type':'application/json',[options.csrfHeader]:options.csrf},
                        body:JSON.stringify({header:payload.header,lines:payload.lines.slice(offset,offset+100)})});
                    const result = await response.json();
                    if (mine !== generation) return null;
                    if (!response.ok || !result.ok || !result.data) throw new Error(result.message || 'Stok belum dapat dibaca.');
                    if (!merged) merged = Object.assign({},result.data,{rows:[]});
                    merged.checked_at = result.data.checked_at;
                    merged.rows.push(...result.data.rows.map(row => Object.assign({},row,{line:Number(row.line)+offset})));
                }
                if (mine !== generation) return null;
                if (JSON.stringify(options.payload()) !== last) { changed(); return null; }
                data = merged; paint(data);
                message('Stok sekarang • dibaca '+data.checked_at+'. Total bahan baku dalam satuan isi; bukan stok fisik atau reservasi.');
                return data;
            } catch (error) {
                if (mine === generation) message(error.name === 'AbortError' ? 'Pemeriksaan terlalu lama. Klik Perbarui stok untuk mencoba lagi.' : 'Stok belum diketahui. '+error.message);
                return null;
            } finally { clearTimeout(deadline); }
        }
        function changed() {
            const value = JSON.stringify(options.payload());
            if (value === last) { paint(data); return; }
            last = value; generation++; data = null;
            if (pending) pending.abort();
            paint(null); clearTimeout(timer); timer = setTimeout(load,450);
        }
        if (refresh) refresh.addEventListener('click',load);
        return {changed:changed, refresh:load, async confirmBeforeSave() {
            const result = await load();
            if (!result) { message('Belum dapat menyimpan: periksa kembali stok dan tujuan, lalu coba lagi.'); return false; }
            const warnings = result.rows.filter(row => row.needs_confirmation || row.warning);
            if (!warnings.length) return true;
            const summary = warnings.map(row => row.name+' — '+(result.warehouse_only ? 'Gudang: '+quantity(row.warehouse,row.uom)
                : 'Divisi: '+quantity(row.division,row.uom)+'; Gudang: '+quantity(row.warehouse,row.uom))+'\n'+(row.warning || 'Saldo perlu diperiksa.')).join('\n\n');
            return window.confirm(summary+'\n\nPastikan kebutuhan tambahan sudah dikonfirmasi. Tetap simpan pengajuan ini?');
        }};
    }};
}());
