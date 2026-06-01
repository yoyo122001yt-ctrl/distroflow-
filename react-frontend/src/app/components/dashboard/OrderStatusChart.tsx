import { useRef, useState } from 'react'
import { motion, useInView } from 'motion/react'

interface StatusData {
  status: string
  count: number
  color: string
  label: string
}

interface OrderStatusChartProps {
  data: StatusData[]
}

const DEFAULT_COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899']

export default function OrderStatusChart({ data }: OrderStatusChartProps) {
  const ref = useRef<HTMLDivElement>(null)
  const inView = useInView(ref, { once: true, margin: '-40px' })
  const [hoveredIndex, setHoveredIndex] = useState<number | null>(null)

  if (!data || data.length === 0) {
    return (
      <div className="flex items-center justify-center h-[300px] text-[var(--color-text-muted)]">
        <p className="text-sm">No order data available</p>
      </div>
    )
  }

  const total = data.reduce((sum, d) => sum + d.count, 0)
  const size = 180
  const cx = size / 2
  const cy = size / 2
  const innerRadius = 55
  const outerRadius = 85
  const strokeWidth = outerRadius - innerRadius

  // Calculate arc segments
  let cumulativePercent = 0
  const arcs = data.map((d, i) => {
    const percent = d.count / total
    const startPercent = cumulativePercent
    const endPercent = cumulativePercent + percent
    cumulativePercent = endPercent

    const startAngle = startPercent * 360 - 90
    const endAngle = endPercent * 360 - 90
    const largeArc = percent > 0.5 ? 1 : 0

    const startRad = (startAngle * Math.PI) / 180
    const endRad = (endAngle * Math.PI) / 180

    const x1 = cx + outerRadius * Math.cos(startRad)
    const y1 = cy + outerRadius * Math.sin(startRad)
    const x2 = cx + outerRadius * Math.cos(endRad)
    const y2 = cy + outerRadius * Math.sin(endRad)

    const path = `M ${x1} ${y1} A ${outerRadius} ${outerRadius} 0 ${largeArc} 1 ${x2} ${y2}`

    return {
      ...d,
      path,
      percent,
      color: d.color || DEFAULT_COLORS[i % DEFAULT_COLORS.length],
      startAngle,
      endAngle,
    }
  })

  return (
    <div ref={ref} className="flex flex-col items-center">
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} className="transform -rotate-90">
          {/* Background ring */}
          <circle
            cx={cx}
            cy={cy}
            r={outerRadius - strokeWidth / 2}
            fill="none"
            stroke="#F1F5F9"
            strokeWidth={strokeWidth}
          />

          {/* Segments */}
          {arcs.map((arc, i) => {
            const isHovered = hoveredIndex === i
            const scale = isHovered ? 1.08 : 1

            return (
              <motion.g
                key={i}
                initial={{ pathLength: 0 }}
                animate={inView ? { pathLength: 1 } : {}}
                transition={{ duration: 0.8, delay: i * 0.15, ease: [0.16, 1, 0.3, 1] }}
                onMouseEnter={() => setHoveredIndex(i)}
                onMouseLeave={() => setHoveredIndex(null)}
                className="cursor-pointer"
                style={{ transformOrigin: `${cx}px ${cy}px`, transform: `scale(${scale})` }}
              >
                <path
                  d={arc.path}
                  fill="none"
                  stroke={arc.color}
                  strokeWidth={strokeWidth}
                  strokeLinecap="round"
                />
              </motion.g>
            )
          })}
        </svg>

        {/* Center text */}
        <motion.div
          className="absolute inset-0 flex flex-col items-center justify-center"
          initial={{ opacity: 0 }}
          animate={inView ? { opacity: 1 } : {}}
          transition={{ delay: 0.6, duration: 0.4 }}
        >
          <span className="text-2xl font-bold text-[var(--color-text-primary)] tabular-nums">
            {total}
          </span>
          <span className="text-[10px] text-[var(--color-text-muted)] uppercase tracking-wider font-medium">
            Total
          </span>
        </motion.div>
      </div>

      {/* Legend */}
      <div className="flex flex-wrap justify-center gap-x-4 gap-y-1.5 mt-4">
        {arcs.map((arc, i) => {
          const isHovered = hoveredIndex === i
          return (
            <motion.div
              key={i}
              className="flex items-center gap-1.5 cursor-default"
              animate={{ opacity: hoveredIndex === null || isHovered ? 1 : 0.5 }}
              transition={{ duration: 0.2 }}
              onMouseEnter={() => setHoveredIndex(i)}
              onMouseLeave={() => setHoveredIndex(null)}
            >
              <span
                className="w-2.5 h-2.5 rounded-full"
                style={{ backgroundColor: arc.color }}
              />
              <span className="text-xs text-[var(--color-text-secondary)] font-medium">
                {arc.label || arc.status}
              </span>
              <span className="text-xs text-[var(--color-text-muted)]">
                ({Math.round(arc.percent * 100)}%)
              </span>
            </motion.div>
          )
        })}
      </div>
    </div>
  )
}
