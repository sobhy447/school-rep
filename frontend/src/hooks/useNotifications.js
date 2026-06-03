import { useState, useCallback, useEffect } from 'react';
import { gradeService } from '../services/gradeService';

export const useNotifications = () => {
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  // Load notifications
  const loadNotifications = useCallback(async () => {
    try {
      setLoading(true);
      const response = await gradeService.getNotifications();
      setNotifications(response.data);
      setUnreadCount(response.data.filter(n => !n.read).length);
      setError(null);
    } catch (err) {
      setError(err.response?.data?.message || 'فشل تحميل الإشعارات');
    } finally {
      setLoading(false);
    }
  }, []);

  // Mark as read
  const markAsRead = useCallback(async (notificationId) => {
    try {
      await gradeService.markNotificationRead(notificationId);

      setNotifications(prev => 
        prev.map(n => n._id === notificationId ? { ...n, read: true } : n)
      );
      setUnreadCount(prev => Math.max(0, prev - 1));
    } catch (err) {
      console.error('Failed to mark notification as read:', err);
    }
  }, []);

  // Mark all as read
  const markAllAsRead = useCallback(async () => {
    try {
      await gradeService.markAllNotificationsRead();

      setNotifications(prev => prev.map(n => ({ ...n, read: true })));
      setUnreadCount(0);
    } catch (err) {
      console.error('Failed to mark all notifications as read:', err);
    }
  }, []);

  // Delete notification
  const deleteNotification = useCallback(async (notificationId) => {
    try {
      await gradeService.deleteNotification(notificationId);

      setNotifications(prev => {
        const filtered = prev.filter(n => n._id !== notificationId);
        setUnreadCount(filtered.filter(n => !n.read).length);
        return filtered;
      });
    } catch (err) {
      console.error('Failed to delete notification:', err);
    }
  }, []);

  // Simulate WebSocket notification (for demo)
  const simulateNotification = useCallback((notification) => {
    setNotifications(prev => [notification, ...prev]);
    setUnreadCount(prev => prev + 1);
  }, []);

  return {
    notifications,
    unreadCount,
    loading,
    error,
    loadNotifications,
    markAsRead,
    markAllAsRead,
    deleteNotification,
    simulateNotification
  };
};
