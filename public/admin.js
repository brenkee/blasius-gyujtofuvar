(function(){
  const bootstrap = window.ADMIN_BOOTSTRAP || {};
  const csrfToken = bootstrap.csrfToken || document.body?.dataset?.csrf || '';
  const me = bootstrap.me || {};
  const mustChange = Boolean(bootstrap.mustChange || document.body?.dataset?.forceProfile === '1');

  const userTableBody = document.getElementById('userTableBody');
  const toastEl = document.getElementById('toast');
  const createForm = document.getElementById('createUserForm');
  const editForm = document.getElementById('editUserForm');
  const profileForm = document.getElementById('profileForm');
  const refreshBtn = document.getElementById('refreshUsers');
  const cancelEditBtn = document.getElementById('cancelEdit');
  const logoutForm = document.getElementById('logoutForm');

  const baseCandidate = typeof bootstrap.baseUrl === 'string' && bootstrap.baseUrl ? bootstrap.baseUrl : '/';
  const baseUrl = baseCandidate.endsWith('/') ? baseCandidate : baseCandidate + '/';

  function withBase(path) {
    if (!path) {
      return baseUrl;
    }
    if (/^https?:\/\//i.test(path)) {
      return path;
    }
    return baseUrl + path.replace(/^\/+/,'');
  }

  const state = {
    users: [],
    editingId: null,
  };

  function showToast(message, type = 'success') {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.remove('error', 'success');
    toastEl.classList.add(type === 'error' ? 'error' : 'success');
    toastEl.hidden = false;
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(()=>{
      toastEl.hidden = true;
    }, 4000);
  }

  function request(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');
    if (options.method && options.method !== 'GET' && options.method !== 'HEAD') {
      if (csrfToken) {
        headers.set('X-CSRF-Token', csrfToken);
      }
      if (!headers.has('Content-Type') && options.body && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
      }
    }
    const finalUrl = withBase(url);
    return fetch(finalUrl, Object.assign({}, options, { headers, credentials: 'same-origin' }))
      .then(async (res)=>{
        let data = null;
        try { data = await res.json(); } catch (err) { data = null; }
        if (!res.ok) {
          const msg = data?.error || 'Ismeretlen hiba történt.';
          throw new Error(msg);
        }
        return data;
      });
  }

  function renderUsers() {
    if (!userTableBody) return;
    userTableBody.innerHTML = '';
    if (!state.users.length) {
      const tr = document.createElement('tr');
      tr.className = 'empty';
      const td = document.createElement('td');
      td.colSpan = 6;
      td.textContent = 'Nincsenek felhasználók.';
      tr.appendChild(td);
      userTableBody.appendChild(tr);
      return;
    }
    state.users.forEach((user)=>{
      const tr = document.createElement('tr');
      tr.dataset.id = String(user.id);
      const lastLogin = user.last_login_at ? new Date(user.last_login_at + 'Z').toLocaleString('hu-HU', { timeZone: 'Europe/Budapest' }) : '—';
      tr.innerHTML = `
        <td>${escapeHtml(user.username)}</td>
        <td>${escapeHtml(user.email)}</td>
        <td>${escapeHtml(user.role)}</td>
        <td>${user.is_active ? '✓' : '✕'}</td>
        <td>${lastLogin}</td>
        <td>
          <div class="user-actions">
            <button type="button" class="btn-edit" data-action="edit">Szerkesztés</button>
            <button type="button" class="btn-delete" data-action="delete" ${user.id === me.id ? 'disabled' : ''}>Törlés</button>
          </div>
        </td>
      `;
      userTableBody.appendChild(tr);
    });
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fetchUsers() {
    return request('auth_api.php?action=users')
      .then((data)=>{
        state.users = Array.isArray(data.users) ? data.users : [];
        renderUsers();
      })
      .catch((err)=>{
        showToast(err.message, 'error');
      });
  }

  function getCheckbox(form, name) {
    const el = form.querySelector(`[name="${name}"]`);
    return el ? !!el.checked : false;
  }

  function handleCreate(e) {
    e.preventDefault();
    if (!createForm) return;
    const formData = new FormData(createForm);
    const payload = {
      username: formData.get('username')?.toString().trim() || '',
      email: formData.get('email')?.toString().trim() || '',
      password: formData.get('password')?.toString() || '',
      role: formData.get('role')?.toString() || 'viewer',
      must_change_password: getCheckbox(createForm, 'must_change_password'),
      is_active: getCheckbox(createForm, 'is_active'),
    };
    request('auth_api.php?action=users', {
      method: 'POST',
      body: JSON.stringify(payload),
    }).then(()=>{
      createForm.reset();
      showToast('Felhasználó létrehozva.');
      fetchUsers();
    }).catch((err)=>{
      showToast(err.message, 'error');
    });
  }

  function setEditForm(user) {
    if (!editForm) return;
    editForm.dataset.empty = 'false';
    state.editingId = user.id;
    editForm.querySelector('[name="id"]').value = user.id;
    editForm.querySelector('[name="username"]').value = user.username;
    editForm.querySelector('[name="email"]').value = user.email;
    editForm.querySelector('[name="role"]').value = user.role;
    editForm.querySelector('[name="password"]').value = '';
    editForm.querySelector('[name="must_change_password"]').checked = !!user.must_change_password;
    editForm.querySelector('[name="is_active"]').checked = !!user.is_active;
    toggleEditButtons(true);
  }

  function resetEditForm() {
    if (!editForm) return;
    editForm.dataset.empty = 'true';
    state.editingId = null;
    editForm.reset();
    toggleEditButtons(false);
  }

  function toggleEditButtons(enabled) {
    const submitBtn = editForm?.querySelector('button[type="submit"]');
    const cancelBtn = document.getElementById('cancelEdit');
    if (submitBtn) submitBtn.disabled = !enabled;
    if (cancelBtn) cancelBtn.disabled = !enabled;
  }

  function handleEditSubmit(e) {
    e.preventDefault();
    if (!editForm || state.editingId == null) return;
    const id = state.editingId;
    const payload = {};
    const username = editForm.querySelector('[name="username"]').value.trim();
    const email = editForm.querySelector('[name="email"]').value.trim();
    const password = editForm.querySelector('[name="password"]').value;
    const role = editForm.querySelector('[name="role"]').value;
    payload.username = username;
    payload.email = email;
    if (password) {
      payload.password = password;
    }
    payload.role = role;
    payload.must_change_password = getCheckbox(editForm, 'must_change_password');
    payload.is_active = getCheckbox(editForm, 'is_active');

    request(`auth_api.php?action=user&id=${encodeURIComponent(id)}`, {
      method: 'PATCH',
      body: JSON.stringify(payload),
    }).then(()=>{
      showToast('Felhasználó módosítva.');
      resetEditForm();
      fetchUsers();
    }).catch((err)=>{
      showToast(err.message, 'error');
    });
  }

  function handleProfileSubmit(e) {
    e.preventDefault();
    if (!profileForm) return;
    const formData = new FormData(profileForm);
    const password = formData.get('password')?.toString() || '';
    const confirm = formData.get('password_confirm')?.toString() || '';
    const payload = {
      username: formData.get('username')?.toString().trim() || '',
      email: formData.get('email')?.toString().trim() || '',
    };
    if (password) {
      if (password !== confirm) {
        showToast('A megadott jelszavak nem egyeznek.', 'error');
        return;
      }
      payload.password = password;
      payload.password_confirm = confirm;
    }
    request('auth_api.php?action=profile', {
      method: 'POST',
      body: JSON.stringify(payload),
    }).then((data)=>{
      showToast('Saját profil frissítve.');
      if (data?.user) {
        profileForm.querySelector('[name="username"]').value = data.user.username || '';
        profileForm.querySelector('[name="email"]').value = data.user.email || '';
      }
      profileForm.querySelector('[name="password"]').value = '';
      profileForm.querySelector('[name="password_confirm"]').value = '';
      fetchUsers();
    }).catch((err)=>{
      showToast(err.message, 'error');
    });
  }

  function handleTableClick(e) {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    const tr = btn.closest('tr');
    if (!tr) return;
    const id = Number(tr.dataset.id);
    if (!id) return;
    if (btn.dataset.action === 'edit') {
      const user = state.users.find((u)=>u.id === id);
      if (user) {
        setEditForm(user);
        window.scrollTo({ top: editForm.offsetTop - 40, behavior: 'smooth' });
      }
    } else if (btn.dataset.action === 'delete') {
      if (btn.disabled) return;
      if (!confirm('Biztosan törlöd ezt a felhasználót?')) return;
      request(`auth_api.php?action=user&id=${encodeURIComponent(id)}`, {
        method: 'DELETE',
      }).then(()=>{
        showToast('Felhasználó törölve.');
        if (state.editingId === id) {
          resetEditForm();
        }
        fetchUsers();
      }).catch((err)=>{
        showToast(err.message, 'error');
      });
    }
  }

  if (createForm) {
    createForm.addEventListener('submit', handleCreate);
  }
  if (editForm) {
    editForm.addEventListener('submit', handleEditSubmit);
  }
  if (cancelEditBtn) {
    cancelEditBtn.addEventListener('click', resetEditForm);
  }
  if (profileForm) {
    profileForm.addEventListener('submit', handleProfileSubmit);
  }
  if (userTableBody) {
    userTableBody.addEventListener('click', handleTableClick);
  }
  if (refreshBtn) {
    refreshBtn.addEventListener('click', fetchUsers);
  }
  if (logoutForm && csrfToken) {
    const hidden = logoutForm.querySelector('input[name="_csrf"]');
    if (hidden) hidden.value = csrfToken;
  }

  fetchUsers();

  if (mustChange) {
    const profileSection = document.getElementById('profileSection');
    if (profileSection) {
      profileSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }
})();
