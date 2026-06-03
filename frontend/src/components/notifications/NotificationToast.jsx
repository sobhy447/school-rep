import React, { useEffect, useState } from 'react';
import { CheckCircle, AlertCircle, X, Bell } from 'lucide-react';

const NotificationToast = ({ notification, onClose, duration = 5000 }) => {
  const [isVisible, setIsVisible] = useState(false);
  const [progress, setProgress] = useState(100);

  useEffect(() => {
    // Animate in
    setTimeout(() => setIsVisible(true), 100);

    // Auto close
    const timer = setTimeout(() => {
      handleClose();
    }, duration);

    // Progress bar
    const interval = setInterval(() => {
      setProgress(prev => {
        if (prev <= 0) {
          clearInterval(interval);
          return 0;
        }
        return prev - (100 / (duration / 100));
      });
    }, 100);

    return () => {
      clearTimeout(timer);
      clearInterval(interval);
    };
  }, [duration]);

  const handleClose = () => {
    setIsVisible(false);
    setTimeout(() => onClose(), 300);
  };

  const getIcon = () => {
    switch (notification.type) {
      case 'teacher_submitted':
        return <CheckCircle className="w-5 h-5 text-blue-600" />;
      case 'head_approved':
        return <CheckCircle className="w-5 h-5 text-purple-600" />;
      case 'head_edited':
        return <AlertCircle className="w-5 h-5 text-amber-600" />;
      default:
        return <Bell className="w-5 h-5 text-gray-600" />;
    }
  };

  const getColors = () => {
    switch (notification.type) {
      case 'teacher_submitted':
        return 'bg-blue-50 border-blue-300';
      case 'head_approved':
        return 'bg-purple-50 border-purple-300';
      case 'head_edited':
        return 'bg-amber-50 border-amber-300';
      default:
        return 'bg-gray-50 border-gray-300';
    }
  };

  return (
    <div
      className={`fixed top-4 left-4 z-50 w-96 transform transition-all duration-300
        ${isVisible ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'}
      `}
    >
      <div className={`rounded-xl border-2 p-4 shadow-lg ${getColors()}`}>
        <div className="flex items-start gap-3">
          {/* Icon */}
          <div className="flex-shrink-0 mt-0.5">
            {getIcon()}
          </div>

          {/* Content */}
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between">
              <h4 className="font-bold text-gray-900">{notification.title}</h4>
              <button
                onClick={handleClose}
                className="p-1 text-gray-400 hover:text-gray-600 rounded transition-colors"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
            <p className="text-sm text-gray-700 mt-1">{notification.message}</p>
          </div>
        </div>

        {/* Progress Bar */}
        <div className="mt-3 h-1 bg-gray-200 rounded-full overflow-hidden">
          <div
            className="h-full bg-indigo-500 transition-all duration-100"
            style={{ width: `${progress}%` }}
          />
        </div>
      </div>
    </div>
  );
};

export default NotificationToast;
