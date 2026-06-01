import { useRef, useEffect, useState } from 'react'
import { motion, useInView } from 'motion/react'

interface SalesData {
  date: string
  amount: number
}

interface SalesTrendChartProps {
  data: SalesData[]
}

function Tooltip({ x, y, value, label, visible }: { x: number; y: number; value: number; label: string; visible: boolean }) {
  if (!visible) return null
  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.9, y: -5 }}
      animate={{ opacity: 1, scale: 1, y: 0 }}
      className="fixed pointer-events-none z-50 bg-[var(--color-text-primary)] text-white text-xs font-semibold px-3 py-1.5 rounded-lg shadow-lg"
      style={{ left: x, top: y - 40, transform: 'translateX(-50%)' }}
    >
      <p>${value.toLocaleString()}</p>
      <p className="text-white/70 text-[10px] font-normal">{label}</p>
    </motion.div>
  )
}

export default function SalesTrendChart({ data }: SalesTrendChartProps) {
  const ref = useRef<HTMLDivElement>(null)
  const inView = useInView(ref, { once: true, margin: '-40px' })
  const [tooltip, setTooltip] = useState<{ x: number; y: number; value: number; label: string; visible: boolean }>({
    x: 0, y: 0, value: 0, label: '', visible: false,
  })

  if (!data || data.length === 0) {
    return (
      <div className="flex items-center justify-center h-[300px] text-[var(--color-text-muted)]">
        <p className="text-sm">No sales data available</p>
      </div>
    )
  }

  const maxAmount = Math.max(...data.map((d) => d.amount), 1)
  const barGap = 8
  const barWidth = Math.max(12, (600 - data.length * barGap) / data.length)
  const chartHeight = 220
  const chartWidth = data.length * (barWidth + barGap)

  return (
    <div ref={ref} className="w-full">
      <div className="overflow-x-auto scrollbar-thin">
        <svg
          width={Math.max(chartWidth, 100)}
          height={chartHeight + 40}
          className="w-full"
          style={{ minWidth: chartWidth }}
        >
          {/* Grid lines */}
          {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
            const y = chartHeight * (1 - ratio)
            return (
              <g key={ratio}>
                <line
                  x1={0} y1={y} x2={chartWidth} y2={y}
                  stroke="#E2E8F0"
                  strokeWidth={1}
                  strokeDasharray={ratio === 0 ? 'none' : '4 4'}
                />
                <text
                  x={-8} y={y + 4}
                  textAnchor="end"
                  fill="#94A3B8"
                  fontSize={10}
                >
                  ${Math.round(maxAmount * ratio).toLocaleString()}
                </text>
              </g>
            )
          })}

          {/* Bars */}
          {data.map((d, i) => {
            const barHeight = (d.amount / maxAmount) * chartHeight
            const x = i * (barWidth + barGap) + barGap / 2
            const y = chartHeight - barHeight

            return (
              <motion.rect
                key={i}
                x={x}
                y={chartHeight}
                width={barWidth}
                height={0}
                rx={4}
                ry={4}
                fill="#3B82F6"
                initial={false}
                animate={inView ? { y, height: barHeight } : {}}
                transition={{
                  duration: 0.6,
                  delay: i * 0.05,
                  ease: [0.16, 1, 0.3, 1],
                }}
                onMouseEnter={(e) => {
                  const rect = (e.target as SVGElement).getBoundingClientRect()
                  setTooltip({
                    x: rect.left + rect.width / 2,
                    y: rect.top,
                    value: d.amount,
                    label: d.date,
                    visible: true,
                  })
                }}
                onMouseLeave={() => setTooltip((t) => ({ ...t, visible: false }))}
                className="cursor-pointer hover:fill-[#2563EB] transition-colors"
              />
            )
          })}

          {/* X-axis labels */}
          {data.map((d, i) => {
            const x = i * (barWidth + barGap) + barWidth / 2 + barGap / 2
            return (
              <text
                key={i}
                x={x}
                y={chartHeight + 20}
                textAnchor="middle"
                fill="#94A3B8"
                fontSize={10}
              >
                {d.date.slice(-2)}
              </text>
            )
          })}
        </svg>
      </div>

      <Tooltip
        x={tooltip.x}
        y={tooltip.y}
        value={tooltip.value}
        label={tooltip.label}
        visible={tooltip.visible}
      />
    </div>
  )
}
