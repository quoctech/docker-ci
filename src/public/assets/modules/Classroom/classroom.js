// ==========================================================================
// Classroom — Teacher: manage classrooms list
// ==========================================================================

function classroomManager() {
    return {
        classrooms: [],
        loading: false,
        showCreate: false,
        submitting: false,
        form: { name: '', subject: '', grade: '', description: '', auto_approve: true },

        async load() {
            this.loading = true;
            const data = await apiGet('/api/classrooms');
            if (data?.status === 'success') this.classrooms = data.data;
            this.loading = false;
        },

        openCreate() {
            this.form = { name: '', subject: '', grade: '', description: '', auto_approve: true };
            this.showCreate = true;
        },

        async submitCreate() {
            if (!this.form.name.trim()) { showToast('error', 'Vui lòng nhập tên lớp học.'); return; }
            this.submitting = true;
            const body = new URLSearchParams({
                name:         this.form.name,
                subject:      this.form.subject || '',
                grade:        this.form.grade || '',
                description:  this.form.description || '',
                auto_approve: this.form.auto_approve ? '1' : '0',
            });
            const data = await apiRequest('/api/classrooms', { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
            this.submitting = false;
            if (data?.status === 'success') {
                showToast('success', 'Tạo lớp học thành công!');
                this.showCreate = false;
                await this.load();
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        viewDetail(c) {
            window.location.href = '/admin/classrooms/' + c.uuid;
        },

        async copyCode(code) {
            await navigator.clipboard.writeText(code);
            showToast('success', 'Đã copy mã lớp: ' + code);
        },

        async confirmDelete(c) {
            const ok = await showConfirm({
                title: 'Xóa lớp học',
                message: 'Xóa lớp "' + c.name + '"? Toàn bộ bài tập và dữ liệu sẽ bị xóa.',
                type: 'danger',
                confirmText: 'Xóa'
            });
            if (!ok) return;
            const data = await apiRequest('/api/classrooms/' + c.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã xóa lớp học.');
                this.classrooms = this.classrooms.filter(x => x.uuid !== c.uuid);
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}

// ==========================================================================
// Classroom — Teacher: classroom detail (members + assignments)
// ==========================================================================

function classroomDetail(uuid) {
    return {
        uuid,
        classroom: null,
        members: [],
        assignments: [],
        tab: 'members',
        loading: false,
        loadingAssignments: false,
        showAssignmentForm: false,
        submittingA: false,
        aForm: { title: '', description: '', due_date: '', max_score: 100, is_published: true, file: null },

        get pendingMembers()  { return this.members.filter(m => m.status === 'pending'); },
        get approvedMembers() { return this.members.filter(m => m.status === 'approved'); },
        get pendingCount()    { return this.pendingMembers.length; },

        async load() {
            this.loading = true;
            const [classData, memberData, assignData] = await Promise.all([
                apiGet('/api/classrooms/' + this.uuid),
                apiGet('/api/classrooms/' + this.uuid + '/members'),
                apiGet('/api/classrooms/' + this.uuid + '/assignments'),
            ]);
            if (classData?.status === 'success')  this.classroom    = classData.data;
            if (memberData?.status === 'success') this.members      = memberData.data;
            if (assignData?.status === 'success') this.assignments  = assignData.data;
            this.loading = false;
        },

        async copyCode() {
            await navigator.clipboard.writeText(this.classroom.code);
            showToast('success', 'Đã copy mã lớp: ' + this.classroom.code);
        },

        async toggleApproval() {
            const data = await apiRequest('/api/classrooms/' + this.uuid + '/toggle-approval', { method: 'PUT' });
            if (data?.status === 'success') {
                this.classroom.auto_approve = data.data.auto_approve ? 1 : 0;
                showToast('success', data.message);
            }
        },

        async approve(m) {
            const data = await apiRequest('/api/classrooms/' + this.uuid + '/members/' + m.id + '/approve', { method: 'PUT' });
            if (data?.status === 'success') {
                m.status = 'approved'; m.joined_at = new Date().toISOString();
                showToast('success', 'Đã duyệt học sinh.');
            }
        },

        async reject(m) {
            const ok = await showConfirm({ title: 'Từ chối', message: 'Từ chối "' + m.full_name + '"?', type: 'warning', confirmText: 'Từ chối' });
            if (!ok) return;
            const data = await apiRequest('/api/classrooms/' + this.uuid + '/members/' + m.id + '/reject', { method: 'PUT' });
            if (data?.status === 'success') {
                this.members = this.members.filter(x => x.id !== m.id);
                showToast('success', 'Đã từ chối.');
            }
        },

        async removeMember(m) {
            const ok = await showConfirm({ title: 'Xóa học sinh', message: 'Xóa "' + m.full_name + '" khỏi lớp?', type: 'danger', confirmText: 'Xóa' });
            if (!ok) return;
            const data = await apiRequest('/api/classrooms/' + this.uuid + '/members/' + m.id, { method: 'DELETE' });
            if (data?.status === 'success') {
                this.members = this.members.filter(x => x.id !== m.id);
                showToast('success', 'Đã xóa khỏi lớp.');
            }
        },

        openCreateAssignment() {
            this.aForm = { title: '', description: '', due_date: '', max_score: 100, is_published: true, file: null };
            this.showAssignmentForm = true;
        },

        async submitAssignment() {
            if (!this.aForm.title.trim()) { showToast('error', 'Vui lòng nhập tiêu đề bài tập.'); return; }
            this.submittingA = true;
            const fd = new FormData();
            fd.append('title',        this.aForm.title);
            fd.append('description',  this.aForm.description || '');
            fd.append('due_date',     this.aForm.due_date || '');
            fd.append('max_score',    this.aForm.max_score || 100);
            fd.append('is_published', this.aForm.is_published ? '1' : '0');
            if (this.aForm.file) fd.append('assignment_file', this.aForm.file);
            const data = await apiRequest('/api/classrooms/' + this.uuid + '/assignments', {
                method: 'POST', body: fd
            });
            this.submittingA = false;
            if (data?.status === 'success') {
                showToast('success', 'Đã đăng bài tập!');
                this.showAssignmentForm = false;
                this.assignments.unshift(data.data);
                this.tab = 'assignments';
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async downloadFile(a) {
            const token = getToken();
            const res = await fetch('/api/assignments/' + a.uuid + '/file', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (!res.ok) { showToast('error', 'Không thể tải file.'); return; }
            const cd  = res.headers.get('Content-Disposition') || '';
            const ext = a.file_path ? a.file_path.split('.').pop() : 'pdf';
            const filename = cd.match(/filename="([^"]+)"/)?.[1] || (a.title + '.' + ext);
            const blob = await res.blob();
            const url  = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url; link.download = filename;
            document.body.appendChild(link); link.click();
            document.body.removeChild(link); URL.revokeObjectURL(url);
        },

        viewAssignment(a) {
            window.location.href = '/admin/classrooms/' + this.uuid + '/assignments/' + a.uuid;
        },

        async deleteAssignment(a) {
            const ok = await showConfirm({ title: 'Xóa bài tập', message: 'Xóa "' + a.title + '"?', type: 'danger', confirmText: 'Xóa' });
            if (!ok) return;
            const data = await apiRequest('/api/assignments/' + a.uuid, { method: 'DELETE' });
            if (data?.status === 'success') {
                this.assignments = this.assignments.filter(x => x.uuid !== a.uuid);
                showToast('success', 'Đã xóa bài tập.');
            }
        },
    };
}

// ==========================================================================
// Classroom — Teacher: grade submissions for an assignment
// ==========================================================================

function assignmentGrader(classroomUuid, assignmentUuid) {
    return {
        classroomUuid,
        assignmentUuid,
        assignment: null,
        submissions: [],
        loading: false,
        showGradeModal: false,
        submittingGrade: false,
        gradingItem: null,
        gradeForm: { score: '', feedback: '' },
        filterStatus: '',
        gradeImages: [],
        loadingGradeImages: false,
        gradeImageLightbox: null,

        get gradedCount() {
            return this.submissions.filter(s => s.status === 'graded').length;
        },
        get filteredSubmissions() {
            if (!this.filterStatus) return this.submissions;
            return this.submissions.filter(s => s.status === this.filterStatus);
        },

        async load() {
            this.loading = true;
            const [assignData, subData] = await Promise.all([
                apiGet('/api/assignments/' + this.assignmentUuid),
                apiGet('/api/assignments/' + this.assignmentUuid + '/submissions'),
            ]);
            if (assignData?.status === 'success') this.assignment  = assignData.data;
            if (subData?.status === 'success')   this.submissions = subData.data.submissions || subData.data;
            this.loading = false;
        },

        async openGrade(s) {
            this.gradingItem       = s;
            this.gradeForm         = { score: s.score ?? '', feedback: s.feedback ?? '' };
            this.gradeImages       = [];
            this.gradeImageLightbox = null;
            this.showGradeModal    = true;
            const imagePaths = s.image_paths || [];
            if (imagePaths.length > 0) {
                this.loadingGradeImages = true;
                const token = localStorage.getItem('access_token');
                const urls = await Promise.all(
                    imagePaths.map(async (_, i) => {
                        try {
                            const r = await fetch('/api/submissions/' + s.uuid + '/images/' + i, {
                                headers: { 'Authorization': 'Bearer ' + token }
                            });
                            if (! r.ok) return null;
                            return URL.createObjectURL(await r.blob());
                        } catch (e) { return null; }
                    })
                );
                this.gradeImages        = urls.filter(Boolean);
                this.loadingGradeImages = false;
            }
        },

        async submitGrade() {
            const score = this.gradeForm.score;
            if (score === '' || score === null) { showToast('error', 'Vui lòng nhập điểm.'); return; }
            if (score < 0 || score > this.assignment.max_score) {
                showToast('error', 'Điểm phải từ 0 đến ' + this.assignment.max_score + '.'); return;
            }
            this.submittingGrade = true;
            const body = new URLSearchParams({ score, feedback: this.gradeForm.feedback || '' });
            const data = await apiRequest('/api/submissions/' + this.gradingItem.uuid + '/grade', {
                method: 'PUT', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            this.submittingGrade = false;
            if (data?.status === 'success') {
                const idx = this.submissions.findIndex(s => s.uuid === this.gradingItem.uuid);
                if (idx !== -1) this.submissions[idx] = { ...this.submissions[idx], ...data.data };
                this.showGradeModal = false;
                showToast('success', 'Chấm điểm thành công!');
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}

// ==========================================================================
// Classroom — Student: my classrooms list
// ==========================================================================

function myClassrooms() {
    return {
        classrooms: [],
        pending: [],
        loading: false,
        showJoin: false,
        joining: false,
        joinCode: '',
        joinMessage: '',
        joinSuccess: false,

        async load() {
            this.loading = true;
            const data = await apiGet('/api/my-classrooms');
            if (data?.status === 'success') {
                this.classrooms = (data.data || []).filter(c => c.status === 'approved');
                this.pending    = (data.data || []).filter(c => c.status === 'pending');
            }
            this.loading = false;
        },

        viewDetail(c) {
            window.location.href = '/admin/my-classrooms/' + c.classroom_uuid;
        },

        async submitJoin() {
            if (!this.joinCode.trim()) return;
            this.joining = true;
            this.joinMessage = '';
            const body = new URLSearchParams({ code: this.joinCode.trim() });
            const data = await apiRequest('/api/classrooms/join', {
                method: 'POST', body,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            });
            this.joining = false;
            if (data?.status === 'success') {
                this.joinSuccess = true;
                this.joinMessage = data.message || 'Yêu cầu tham gia đã được gửi!';
                this.joinCode = '';
                await this.load();
            } else {
                this.joinSuccess = false;
                this.joinMessage = data?.message || 'Có lỗi xảy ra.';
            }
        },

        async cancelRequest(m) {
            const ok = await showConfirm({ title: 'Hủy yêu cầu', message: 'Hủy yêu cầu tham gia lớp "' + m.classroom_name + '"?', type: 'warning', confirmText: 'Hủy yêu cầu' });
            if (!ok) return;
            const data = await apiRequest('/api/my-classrooms/' + m.classroom_uuid + '/leave', { method: 'DELETE' });
            if (data?.status === 'success') {
                this.pending = this.pending.filter(x => x.classroom_id !== m.classroom_id);
                showToast('success', 'Đã hủy yêu cầu tham gia.');
            }
        },
    };
}

// ==========================================================================
// Classroom — Student: my classroom detail (view & submit assignments)
// ==========================================================================

function myClassroomDetail(uuid) {
    return {
        uuid,
        classroom: null,
        assignments: [],
        loading: false,

        showSubmitModal: false,
        showViewModal: false,
        submitting: false,
        submittingItem: null,
        viewingItem: null,
        submitForm: { content: '', images: [], imagePreviews: [] },

        viewImages: [],
        loadingViewImages: false,
        lightboxSrc: null,

        async load() {
            this.loading = true;
            const [classData, assignData] = await Promise.all([
                apiGet('/api/my-classrooms/' + this.uuid),
                apiGet('/api/my-classrooms/' + this.uuid + '/assignments'),
            ]);
            if (classData?.status === 'success')  this.classroom   = classData.data;
            if (assignData?.status === 'success') this.assignments = assignData.data || [];
            this.loading = false;
        },

        openSubmit(a) {
            this.submittingItem = a;
            this.submitForm     = { content: '', images: [], imagePreviews: [] };
            this.showSubmitModal = true;
        },

        closeSubmitModal() {
            this.submitForm.imagePreviews.forEach(url => URL.revokeObjectURL(url));
            this.submitForm = { content: '', images: [], imagePreviews: [] };
            this.showSubmitModal = false;
        },

        handleImageSelect(e) {
            const files     = Array.from(e.target.files || []);
            const remaining = 10 - this.submitForm.images.length;
            const toAdd     = files.slice(0, remaining);
            toAdd.forEach(f => {
                this.submitForm.images.push(f);
                this.submitForm.imagePreviews.push(URL.createObjectURL(f));
            });
            if (files.length > remaining) {
                showToast('warning', 'Tối đa 10 ảnh. Đã bỏ ' + (files.length - remaining) + ' ảnh.');
            }
            e.target.value = '';
        },

        removeImage(idx) {
            URL.revokeObjectURL(this.submitForm.imagePreviews[idx]);
            this.submitForm.images.splice(idx, 1);
            this.submitForm.imagePreviews.splice(idx, 1);
        },

        async submitWork() {
            if (! this.submitForm.content.trim() && this.submitForm.images.length === 0) {
                showToast('error', 'Vui lòng chụp ảnh bài làm hoặc nhập ghi chú.'); return;
            }
            this.submitting = true;
            const fd = new FormData();
            if (this.submitForm.content.trim()) fd.append('content', this.submitForm.content.trim());
            this.submitForm.images.forEach(img => fd.append('images[]', img));

            const data = await apiRequest('/api/assignments/' + this.submittingItem.uuid + '/submit', {
                method: 'POST', body: fd
            });
            this.submitting = false;
            if (data?.status === 'success') {
                showToast('success', 'Nộp bài thành công!');
                this.closeSubmitModal();
                const idx = this.assignments.findIndex(x => x.uuid === this.submittingItem.uuid);
                if (idx !== -1) {
                    this.assignments[idx] = { ...this.assignments[idx], my_submission: data.data };
                }
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },

        async viewSubmission(a) {
            this.viewingItem       = a;
            this.viewImages        = [];
            this.loadingViewImages = false;
            this.showViewModal     = true;
            const imagePaths = a.my_submission?.image_paths || [];
            if (imagePaths.length > 0) {
                this.loadingViewImages = true;
                const urls = await Promise.all(
                    imagePaths.map((_, i) => this.fetchImageBlobUrl(a.my_submission.uuid, i))
                );
                this.viewImages        = urls.filter(Boolean);
                this.loadingViewImages = false;
            }
        },

        async fetchImageBlobUrl(subUuid, index) {
            try {
                const token = localStorage.getItem('access_token');
                const resp  = await fetch('/api/submissions/' + subUuid + '/images/' + index, {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                if (! resp.ok) return null;
                const blob = await resp.blob();
                return URL.createObjectURL(blob);
            } catch (e) {
                return null;
            }
        },

        openLightbox(src) { this.lightboxSrc = src; },

        async downloadAssignmentFile(a) {
            try {
                const token = localStorage.getItem('access_token');
                const resp  = await fetch('/api/assignments/' + a.uuid + '/file', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                if (! resp.ok) { showToast('error', 'Không thể tải file.'); return; }
                const blob = await resp.blob();
                const url  = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href  = url;
                const ext  = a.file_path?.split('.').pop() || 'pdf';
                link.download = (a.title || 'de-bai') + '.' + ext;
                link.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                showToast('error', 'Tải file thất bại.');
            }
        },

        async leaveClassroom() {
            const ok = await showConfirm({ title: 'Rời lớp học', message: 'Bạn có chắc muốn rời lớp "' + this.classroom?.name + '"?', type: 'danger', confirmText: 'Rời lớp' });
            if (! ok) return;
            const data = await apiRequest('/api/my-classrooms/' + this.uuid + '/leave', { method: 'DELETE' });
            if (data?.status === 'success') {
                showToast('success', 'Đã rời lớp học.');
                window.location.href = '/admin/my-classrooms';
            } else {
                showToast('error', data?.message || 'Có lỗi xảy ra.');
            }
        },
    };
}
