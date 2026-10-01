function loadPage(page = 1) {
  const search = document.getElementById('search').value;
  const limit = document.getElementById('limit').value;

  fetch(base_url + 'admin/ajax_kecamatan?page=' + page + '&search=' + search + '&limit=' + limit)
    .then(res => res.text())
    .then(html => {
      document.getElementById('kecamatan-container').innerHTML = html;
    });
}

document.getElementById('search').addEventListener('input', () => loadPage(1));
document.getElementById('limit').addEventListener('change', () => loadPage(1));

window.onload = function() {
  loadPage(1);
};