import { useState, useCallback } from 'react';
import { gradeService } from '../services/gradeService';

export const useAdminSettings = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [settings, setSettings] = useState({
    firstPeriodEnabled: true,
    secondPeriodEnabled: true,
    workEnabled: true,
    examEnabled: true
  });
  const [approvals, setApprovals] = useState([]);
  const [classes, setClasses] = useState([]);
  const [subjects, setSubjects] = useState([]);

  // Load current settings
  const loadSettings = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getAdminSettings();
      setSettings(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الإعدادات');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load all approvals for unlock
  const loadApprovals = useCallback(async (filters = {}) => {
    try {
      setLoading(true);
      const response = await gradeService.getAllApprovals(filters);
      setApprovals(response.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الاعتمادات');
    } finally {
      setLoading(false);
    }
  }, []);

  // Load classes and subjects
  const loadClassesAndSubjects = useCallback(async () => {
    try {
      setLoading(true);
      const [classesRes, subjectsRes] = await Promise.all([
        gradeService.getAllClasses(),
        gradeService.getAllSubjects()
      ]);
      setClasses(classesRes.data);
      setSubjects(subjectsRes.data);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل البيانات');
    } finally {
      setLoading(false);
    }
  }, []);

  // Update settings
  const updateSettings = useCallback(async (newSettings) => {
    try {
      setLoading(true);
      await gradeService.updateAdminSettings(newSettings);
      setSettings(prev => ({ ...prev, ...newSettings }));
      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحديث الإعدادات');
      return false;
    } finally {
      setLoading(false);
    }
  }, []);

  // Unlock approval
  const unlockApproval = useCallback(async (data) => {
    try {
      setLoading(true);
      await gradeService.unlockApproval(data);

      // Refresh approvals list
      await loadApprovals();
      setError(null);
      return true;
    } catch (err) {
      setError(err.response?.data?.message || 'فشل فك الاعتماد');
      return false;
    } finally {
      setLoading(false);
    }
  }, [loadApprovals]);

  return {
    loading,
    error,
    settings,
    approvals,
    classes,
    subjects,
    loadSettings,
    loadApprovals,
    loadClassesAndSubjects,
    updateSettings,
    unlockApproval
  };
};
