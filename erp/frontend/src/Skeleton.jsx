// هياكل تحميل رمادية (Skeletons) تظهر أثناء جلب البيانات بدل الشاشة الفارغة.

// شريط رمادي واحد بعرض اختياري
export function SkelBar({ w = '100%', h = 14, style }) {
  return <span className="skel" style={{ width: w, height: h, display: 'block', ...style }} />
}

// جدول وهمي بعدد صفوف/أعمدة محدّد — يطابق شكل الجداول الحقيقية
export function SkeletonTable({ cols = 5, rows = 6 }) {
  return (
    <table className="table">
      <thead><tr>{Array.from({ length: cols }).map((_, i) => (
        <th key={i}><SkelBar w="60%" h={11} /></th>
      ))}</tr></thead>
      <tbody>
        {Array.from({ length: rows }).map((_, r) => (
          <tr key={r}>{Array.from({ length: cols }).map((_, c) => (
            <td key={c}><SkelBar w={`${50 + ((r + c) % 5) * 10}%`} /></td>
          ))}</tr>
        ))}
      </tbody>
    </table>
  )
}

// شبكة بطاقات مؤشرات وهمية (للوحة المعلومات)
export function SkeletonCards({ count = 6 }) {
  return (
    <div className="kpi-grid">
      {Array.from({ length: count }).map((_, i) => (
        <div className="kpi" key={i}>
          <SkelBar w="40%" h={10} style={{ marginBottom: 12 }} />
          <SkelBar w="70%" h={22} />
        </div>
      ))}
    </div>
  )
}
