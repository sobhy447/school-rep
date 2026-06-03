import api from './api';

export const gradeService = {
  // Teacher APIs
  getTeacherClasses: () => api.get('/grades/teacher/classes'),

  getClassGrades: (classId, subjectId, period) => 
    api.get(`/grades/class/${classId}/subject/${subjectId}?period=${period}`),

  saveGrades: (data) => api.post('/grades/save', data),

  submitGrades: (data) => api.post('/grades/submit', data),

  getStudentExcuses: (studentId, subjectId, period) =>
    api.get(`/grades/excuses/${studentId}?subject=${subjectId}&period=${period}`),

  getApprovalStatus: (classId, subjectId, period, type) =>
    api.get(`/grades/approval/${classId}/${subjectId}?period=${period}&type=${type}`),

  // Head of Department APIs
  getHeadDepartmentClasses: () => api.get('/grades/head/department-classes'),

  getTeacherGradesForReview: (classId, teacherId, subjectId, period) =>
    api.get(`/grades/head/review/${classId}/${teacherId}/${subjectId}?period=${period}`),

  editGradeAsHead: (data) => api.post('/grades/head/edit', data),

  approveDepartment: (data) => api.post('/grades/head/approve-department', data),

  getDepartmentApprovalStatus: (period) =>
    api.get(`/grades/head/dept-status?period=${period}`),

  // Control APIs
  getAllGrades: (params) => api.get('/grades/control/all', { params }),

  getPrintData: (params) => api.get('/grades/control/print', { params }),

  // Admin APIs
  unlockApproval: (data) => api.post('/grades/admin/unlock', data),

  getAllGradesAdmin: (params) => api.get('/grades/admin/all', { params }),

  getAdminSettings: () => api.get('/admin/settings'),

  updateAdminSettings: (data) => api.post('/admin/settings', data),

  getAllApprovals: (filters) => api.get('/admin/approvals', { params: filters }),

  // Excuse APIs
  getExcuses: (filters) => api.get('/excuses', { params: filters }),

  addExcuse: (data) => api.post('/excuses', data),

  deleteExcuse: (id) => api.delete(`/excuses/${id}`),

  getClassStudents: (classId) => api.get(`/classes/${classId}/students`),

  getAllClasses: () => api.get('/classes'),

  getAllSubjects: () => api.get('/subjects'),

  // Notification APIs
  getNotifications: () => api.get('/notifications'),

  markNotificationRead: (id) => api.post(`/notifications/${id}/read`),

  markAllNotificationsRead: () => api.post('/notifications/read-all'),

  deleteNotification: (id) => api.delete(`/notifications/${id}`),
};

export default gradeService;
