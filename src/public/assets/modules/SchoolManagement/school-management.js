// ==========================================================================
// SchoolManagement — Center Manager (centers/index.php)
// ==========================================================================

function centerManager() {
    return {
        centers: [],
        loading: false,
        showModal: false,
        submitting: false,
        editingId: null,
        form: { name: '', address: '', phone: '', email: '' },

        async load() {
            this.loading = true;
            const data = await apiGet('/api/school-management/centers');
            if (data?.status === 'success') this.centers = data.data;
            this.loading = false;
        },

        openCreate() {
            this.editingId = null;
            this.form = { name: '', address: '', phone: '', email: '' };
            this.showModal = true;
        },

        openEdit(c) {
            this.editingId = c.uuid;
            this.form = { name: c.name, address: c.address || '', phone: c.phone || '', email: c.email || '' };
            this.showModal = true;
        },

        async submitForm() {
            if (!this.form.name.trim()) { showToast('error', 'Vui lòng nhập tên trung tâm.'); return; }
            this.submitting = true;

            const body   = new URLSearchParams(this.form);
            const url    = this.editingId ? '/api/school-management/centers/' + this.editingId : '/api/school-management/centers';
            const method = this.editingId ? 'PUT' : 'POST';

            const data = await apiRequest(url, { method, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submitting = false;

            if (data?.status === 'success') {
                showToast('success', this.editingId ? 'Cập nhật thành công!' : 'Tạo trung tâm thành công!');
                this.showModal = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async confirmDelete(c) {
            const ok = await showConfirm({
                title:       'Xóa trung tâm',
                message:     `Xóa trung tâm "${c.name}"? Hành động này không thể hoàn tác.`,
                type:        'danger',
                confirmText: 'Xóa',
            });
            if (!ok) return;
            const data = await apiRequest('/api/school-management/centers/' + c.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã xóa trung tâm.');
                this.centers = this.centers.filter(x => x.uuid !== c.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}

// ==========================================================================
// SchoolManagement — Branch Manager (branches/index.php)
// ==========================================================================

function branchManager() {
    return {
        branches: [],
        centers: [],
        loading: false,
        showModal: false,
        submitting: false,
        editingId: null,
        form: { center_uuid: '', name: '', address: '', phone: '', email: '', manager: '' },

        async load() {
            this.loading = true;
            const [bData, cData] = await Promise.all([
                apiGet('/api/school-management/branches'),
                apiGet('/api/school-management/centers'),
            ]);
            if (bData?.status === 'success') this.branches = bData.data;
            if (cData?.status === 'success') this.centers  = cData.data;
            this.loading = false;
        },

        openCreate() {
            this.editingId = null;
            this.form = { center_uuid: '', name: '', address: '', phone: '', email: '', manager: '' };
            this.showModal = true;
        },

        openEdit(b) {
            this.editingId = b.uuid;
            this.form = {
                center_uuid: b.center_uuid || '',
                name:        b.name,
                address:     b.address || '',
                phone:       b.phone   || '',
                email:       b.email   || '',
                manager:     b.manager || '',
            };
            this.showModal = true;
        },

        async submitForm() {
            if (!this.form.name.trim())    { showToast('error', 'Vui lòng nhập tên chi nhánh.'); return; }
            if (!this.form.address.trim()) { showToast('error', 'Vui lòng nhập địa chỉ.'); return; }
            if (!this.form.manager.trim()) { showToast('error', 'Vui lòng nhập người phụ trách.'); return; }
            if (!this.form.phone.trim())   { showToast('error', 'Vui lòng nhập số điện thoại.'); return; }
            if (!this.form.email.trim())   { showToast('error', 'Vui lòng nhập email.'); return; }
            this.submitting = true;

            const body   = new URLSearchParams(this.form);
            const url    = this.editingId ? '/api/school-management/branches/' + this.editingId : '/api/school-management/branches';
            const method = this.editingId ? 'PUT' : 'POST';

            const data = await apiRequest(url, { method, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submitting = false;

            if (data?.status === 'success') {
                showToast('success', this.editingId ? 'Cập nhật thành công!' : 'Tạo chi nhánh thành công!');
                this.showModal = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async confirmDelete(b) {
            const ok = await showConfirm({
                title:       'Xóa chi nhánh',
                message:     `Xóa chi nhánh "${b.name}"? Hành động này không thể hoàn tác.`,
                type:        'danger',
                confirmText: 'Xóa',
            });
            if (!ok) return;
            const data = await apiRequest('/api/school-management/branches/' + b.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã xóa chi nhánh.');
                this.branches = this.branches.filter(x => x.uuid !== b.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        viewDetail(b) {
            window.location.href = '/admin/school-management/branches/' + b.uuid;
        },
    };
}

// ==========================================================================
// SchoolManagement — Branch Detail (branches/detail.php)
// ==========================================================================

function branchDetail(uuid) {
    return {
        uuid,
        branch: null,
        rooms: [],
        loading: false,

        showEditBranch: false,
        submittingBranch: false,
        branchForm: { name: '', address: '', phone: '', email: '', manager: '' },

        showRoomModal: false,
        submittingRoom: false,
        editingRoomId: null,
        roomForm: { name: '', capacity: '', room_type: '' },

        async init() {
            this.loading = true;
            const [bData, rData] = await Promise.all([
                apiGet('/api/school-management/branches/' + uuid),
                apiGet('/api/school-management/rooms?branch_uuid=' + uuid),
            ]);
            if (bData?.status === 'success') this.branch = bData.data;
            if (rData?.status === 'success') this.rooms  = rData.data;
            this.loading = false;
        },

        openEditBranch() {
            this.branchForm = {
                name:    this.branch.name    || '',
                address: this.branch.address || '',
                phone:   this.branch.phone   || '',
                email:   this.branch.email   || '',
                manager: this.branch.manager || '',
            };
            this.showEditBranch = true;
        },

        async submitBranch() {
            if (!this.branchForm.name.trim())    { showToast('error', 'Vui lòng nhập tên chi nhánh.'); return; }
            if (!this.branchForm.address.trim()) { showToast('error', 'Vui lòng nhập địa chỉ.'); return; }
            if (!this.branchForm.manager.trim()) { showToast('error', 'Vui lòng nhập người phụ trách.'); return; }
            if (!this.branchForm.phone.trim())   { showToast('error', 'Vui lòng nhập số điện thoại.'); return; }
            if (!this.branchForm.email.trim())   { showToast('error', 'Vui lòng nhập email.'); return; }
            this.submittingBranch = true;

            const body = new URLSearchParams(this.branchForm);
            const data = await apiRequest('/api/school-management/branches/' + uuid, {
                method: 'PUT', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            });
            this.submittingBranch = false;

            if (data?.status === 'success') {
                showToast('success', 'Cập nhật chi nhánh thành công!');
                this.branch = data.data;
                this.showEditBranch = false;
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        openCreateRoom() {
            this.editingRoomId = null;
            this.roomForm = { name: '', capacity: '', room_type: '' };
            this.showRoomModal = true;
        },

        openEditRoom(r) {
            this.editingRoomId = r.uuid;
            this.roomForm = { name: r.name, capacity: r.capacity || '', room_type: r.room_type || '' };
            this.showRoomModal = true;
        },

        async submitRoom() {
            if (!this.roomForm.name.trim()) { showToast('error', 'Vui lòng nhập tên phòng.'); return; }
            this.submittingRoom = true;

            const payload = { ...this.roomForm, branch_uuid: uuid };
            const body    = new URLSearchParams(payload);
            const url     = this.editingRoomId
                ? '/api/school-management/rooms/' + this.editingRoomId
                : '/api/school-management/rooms';
            const method  = this.editingRoomId ? 'PUT' : 'POST';

            const data = await apiRequest(url, { method, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submittingRoom = false;

            if (data?.status === 'success') {
                showToast('success', this.editingRoomId ? 'Cập nhật phòng thành công!' : 'Thêm phòng thành công!');
                this.showRoomModal = false;
                const rData = await apiGet('/api/school-management/rooms?branch_uuid=' + uuid);
                if (rData?.status === 'success') this.rooms = rData.data;
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async confirmDeleteRoom(r) {
            const ok = await showConfirm({
                title:       'Xóa phòng',
                message:     `Xóa phòng "${r.name}"?`,
                type:        'danger',
                confirmText: 'Xóa',
            });
            if (!ok) return;
            const data = await apiRequest('/api/school-management/rooms/' + r.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã xóa phòng.');
                this.rooms = this.rooms.filter(x => x.uuid !== r.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}

// ==========================================================================
// SchoolManagement — Room Manager (rooms/index.php)
// ==========================================================================

function roomManager() {
    return {
        rooms: [],
        branches: [],
        loading: false,
        filterBranch: '',
        showModal: false,
        submitting: false,
        editingId: null,
        form: { name: '', branch_uuid: '', capacity: '', room_type: '' },

        get filteredRooms() {
            if (!this.filterBranch) return this.rooms;
            return this.rooms.filter(r => r.branch_uuid === this.filterBranch);
        },

        async load() {
            this.loading = true;
            const [rData, bData] = await Promise.all([
                apiGet('/api/school-management/rooms'),
                apiGet('/api/school-management/branches'),
            ]);
            if (rData?.status === 'success') this.rooms    = rData.data;
            if (bData?.status === 'success') this.branches = bData.data;
            this.loading = false;
        },

        openCreate() {
            this.editingId = null;
            this.form = { name: '', branch_uuid: this.filterBranch || '', capacity: '', room_type: '' };
            this.showModal = true;
        },

        openEdit(r) {
            this.editingId = r.uuid;
            this.form = {
                name:        r.name,
                branch_uuid: r.branch_uuid || '',
                capacity:    r.capacity    || '',
                room_type:   r.room_type   || '',
            };
            this.showModal = true;
        },

        async submitForm() {
            if (!this.form.name.trim())   { showToast('error', 'Vui lòng nhập tên phòng.'); return; }
            if (!this.form.branch_uuid)   { showToast('error', 'Vui lòng chọn chi nhánh.'); return; }
            this.submitting = true;

            const body   = new URLSearchParams(this.form);
            const url    = this.editingId ? '/api/school-management/rooms/' + this.editingId : '/api/school-management/rooms';
            const method = this.editingId ? 'PUT' : 'POST';

            const data = await apiRequest(url, { method, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submitting = false;

            if (data?.status === 'success') {
                showToast('success', this.editingId ? 'Cập nhật thành công!' : 'Thêm phòng thành công!');
                this.showModal = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async confirmDelete(r) {
            const ok = await showConfirm({
                title:       'Xóa phòng',
                message:     `Xóa phòng "${r.name}" (${r.branch_name || ''})?`,
                type:        'danger',
                confirmText: 'Xóa',
            });
            if (!ok) return;
            const data = await apiRequest('/api/school-management/rooms/' + r.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã xóa phòng.');
                this.rooms = this.rooms.filter(x => x.uuid !== r.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}
