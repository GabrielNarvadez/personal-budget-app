// Tab navigation
document.querySelectorAll('.nb').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.nb').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab).classList.add('active');
        window.scrollTo(0, 0);
    });
});

// Type toggle
document.querySelectorAll('.tb').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tb').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('txnType').value = btn.dataset.t;
        const sel = document.getElementById('catSel');
        sel.querySelectorAll('option[data-t]').forEach(o => {
            o.style.display = o.dataset.t === btn.dataset.t ? '' : 'none';
            o.disabled = o.dataset.t !== btn.dataset.t;
        });
        sel.value = '';
    });
});

// Sheets
function openSheet(id) {
    document.getElementById(id).classList.add('open');
    document.getElementById(id + '-bk').classList.add('show');
}
function closeSheet(id) {
    document.getElementById(id).classList.remove('open');
    document.getElementById(id + '-bk').classList.remove('show');
}

// Receipt viewer
function showR(src) {
    document.getElementById('rimg').src = src;
    document.getElementById('rov').classList.add('show');
}

// File input label
document.querySelectorAll('.fbtn input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
        const label = input.closest('.fbtn');
        const name = input.files.length ? '📎 ' + input.files[0].name.substring(0, 12) : '📎 Receipt';
        label.childNodes[0].textContent = name;
    });
});
