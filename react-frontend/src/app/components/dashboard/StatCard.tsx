import { useEffect, useRef } from 'react'
import { motion, useInView, useMotionValue, useTransform, animate } from 'motion/react'
import type { LucideIcon } from 'lucide-react'

interface StatCardProps {
  label: string
  value: number
  icon: LucideIcon
  color: string
  bg: string
  subtext?: string
  isCurrency?: boolean
  delay?: number
}

export default function StatCard({ label, value, icon: Icon, color, bg, subtext, isCurrency, delay = 0 }: StatCardProps) {
  const ref = useRef<HTMLDivElement>(null)
  const inView = useInView(ref, { once: true, margin: '-40px' })
  const count = useMotionValue(0)
  const rounded = useTransform(count, (v) => Math.round(v))
  const displayValue = useTransform(rounded, (v) => {
    if (isCurrency) {
      return '$' + v.toLocaleString()
    }
    return v.toLocaleString()
  })

  useEffect(() => {
    if (!inView) return
    const controls = animate(count, value, {
      duration: 1.2,
      delay: delay + 0.15,
      ease: [0.16, 1, 0.3, 1],
    })
    return controls.stop
  }, [inView, value, delay, count])

  return (
    <motion.div
      ref={ref}
      initial={{ opacity: 0, y: 20, scale: 0.96 }}
      animate={inView ? { opacity: 1, y: 0, scale: 1 } : {}}
      transition={{ duration: 0.5, delay, ease: [0.16, 1, 0.3, 1] }}
      className="stat-card group cursor-default"
    >
      <div className="flex items-center justify-between mb-3">
        <div className={`p-2.5 rounded-xl ${bg} transition-transform duration-300 group-hover:scale-110`}>
          <Icon size={22} className={color} />
        </div>
      </div>
      <motion.p className="text-2xl font-bold text-[var(--color-text-primary)] tabular-nums">
        {displayValue}
      </motion.p>
      <p className="text-sm text-[var(--color-text-secondary)] mt-0.5 font-medium">{label}</p>
      {subtext && (
        <p className="text-xs text-[var(--color-text-muted)] mt-1.5">{subtext}</p>
      )}
    </motion.div>
  )
}
