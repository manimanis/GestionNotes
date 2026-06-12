<template>
  <div class="boxplot-container" ref="container">
    <svg :width="svgWidth" :height="svgHeight" :viewBox="`0 0 ${svgWidth} ${svgHeight}`">
      <!-- Y-axis labels -->
      <text
        v-for="tick in yTicks"
        :key="tick"
        :x="margin.left - 8"
        :y="yScale(tick) + 4"
        text-anchor="end"
        class="axis-label"
      >{{ tick }}</text>

      <!-- Y-axis grid lines -->
      <line
        v-for="tick in yTicks"
        :key="'grid-' + tick"
        :x1="margin.left"
        :y1="yScale(tick)"
        :x2="svgWidth - margin.right"
        :y2="yScale(tick)"
        class="grid-line"
      />

      <!-- Y-axis line -->
      <line
        :x1="margin.left"
        :y1="margin.top"
        :x2="margin.left"
        :y2="svgHeight - margin.bottom"
        class="axis-line"
      />

      <!-- Boxplots -->
      <g v-for="(item, index) in processedData" :key="index">
        <!-- Invisible hit area for hover -->
        <rect
          :x="boxCenter(index) - boxWidth"
          :y="margin.top"
          :width="boxWidth * 2"
          :height="svgHeight - margin.top - margin.bottom"
          fill="transparent"
          style="cursor: pointer;"
          @mouseenter="showTooltip(index, $event)"
          @mouseleave="hideTooltip"
        />

        <!-- Whisker lines (min to Q1 and Q3 to max) -->
        <line
          :x1="boxCenter(index)"
          :y1="yScale(item.stats.min)"
          :x2="boxCenter(index)"
          :y2="yScale(item.stats.q1)"
          class="whisker"
          style="pointer-events: none;"
        />
        <line
          :x1="boxCenter(index)"
          :y1="yScale(item.stats.q3)"
          :x2="boxCenter(index)"
          :y2="yScale(item.stats.max)"
          class="whisker"
          style="pointer-events: none;"
        />

        <!-- Min/Max caps -->
        <line
          :x1="boxCenter(index) - capWidth / 2"
          :y1="yScale(item.stats.min)"
          :x2="boxCenter(index) + capWidth / 2"
          :y2="yScale(item.stats.min)"
          class="whisker"
          style="pointer-events: none;"
        />
        <line
          :x1="boxCenter(index) - capWidth / 2"
          :y1="yScale(item.stats.max)"
          :x2="boxCenter(index) + capWidth / 2"
          :y2="yScale(item.stats.max)"
          class="whisker"
          style="pointer-events: none;"
        />

        <!-- Box (Q1 to Q3) -->
        <rect
          :x="boxCenter(index) - boxWidth / 2"
          :y="yScale(item.stats.q3)"
          :width="boxWidth"
          :height="yScale(item.stats.q1) - yScale(item.stats.q3)"
          class="box"
          :fill="boxColor(index)"
          fill-opacity="0.3"
          :stroke="boxColor(index)"
          stroke-width="1.5"
          style="pointer-events: none;"
        />

        <!-- Median line -->
        <line
          :x1="boxCenter(index) - boxWidth / 2"
          :y1="yScale(item.stats.median)"
          :x2="boxCenter(index) + boxWidth / 2"
          :y2="yScale(item.stats.median)"
          class="median-line"
          :stroke="boxColor(index)"
          style="pointer-events: none;"
        />

        <!-- Outliers -->
        <circle
          v-for="(outlier, oi) in item.stats.outliers"
          :key="'out-' + index + '-' + oi"
          :cx="boxCenter(index)"
          :cy="yScale(outlier)"
          r="3"
          class="outlier"
          style="pointer-events: none;"
        />

        <!-- Mean diamond -->
        <polygon
          :points="meanDiamondPoints(index, item.stats.mean)"
          class="mean-marker"
          style="pointer-events: none;"
        />

        <!-- Label -->
        <text
          :x="boxCenter(index)"
          :y="svgHeight - margin.bottom + 16"
          text-anchor="middle"
          class="x-label"
          style="pointer-events: none;"
        >{{ item.label }}</text>

        <!-- Median value on top -->
        <text
          :x="boxCenter(index)"
          :y="yScale(item.stats.max) - 8"
          text-anchor="middle"
          class="value-label"
          style="pointer-events: none;"
        >{{ item.stats.median.toFixed(1) }}</text>
      </g>
    </svg>

    <!-- Tooltip -->
    <div
      v-if="tooltip.visible"
      class="boxplot-tooltip"
      :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px' }"
    >
      <div class="tooltip-title">{{ tooltip.label }}</div>
      <div class="tooltip-row">
        <span class="tooltip-icon">◆</span>
        <span>Moyenne :</span>
        <span class="tooltip-value">{{ tooltip.mean }}</span>
      </div>
      <div class="tooltip-row">
        <span class="tooltip-icon">σ</span>
        <span>Écart type :</span>
        <span class="tooltip-value">{{ tooltip.stdDev }}</span>
      </div>
      <div class="tooltip-divider"></div>
      <div class="tooltip-row">
        <span class="tooltip-icon">▼</span>
        <span>Minimum :</span>
        <span class="tooltip-value">{{ tooltip.min }}</span>
      </div>
      <div class="tooltip-row">
        <span class="tooltip-icon">Q1</span>
        <span>1er quartile :</span>
        <span class="tooltip-value">{{ tooltip.q1 }}</span>
      </div>
      <div class="tooltip-row">
        <span class="tooltip-icon">━</span>
        <span>Médiane :</span>
        <span class="tooltip-value">{{ tooltip.median }}</span>
      </div>
      <div class="tooltip-row">
        <span class="tooltip-icon">Q3</span>
        <span>3e quartile :</span>
        <span class="tooltip-value">{{ tooltip.q3 }}</span>
      </div>
      <div class="tooltip-row">
        <span class="tooltip-icon">▲</span>
        <span>Maximum :</span>
        <span class="tooltip-value">{{ tooltip.max }}</span>
      </div>
      <div class="tooltip-row tooltip-count">
        <span>N = {{ tooltip.count }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, reactive } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    required: true
    // [{ label: string, values: number[] }]
  },
  min: { type: Number, default: 0 },
  max: { type: Number, default: 20 },
  width: { type: Number, default: 500 },
  height: { type: Number, default: 300 }
})

const container = ref(null)

const margin = { top: 20, right: 20, bottom: 35, left: 45 }

const svgWidth = computed(() => props.width)
const svgHeight = computed(() => props.height)

const yTicks = computed(() => {
  const ticks = []
  for (let i = props.min; i <= props.max; i += 2) {
    ticks.push(i)
  }
  return ticks
})

const yScale = (val) => {
  const range = props.max - props.min
  return margin.top + (1 - (val - props.min) / range) * (svgHeight.value - margin.top - margin.bottom)
}

const boxWidth = computed(() => {
  const available = svgWidth.value - margin.left - margin.right
  const count = props.data.length || 1
  return Math.min(50, available / count * 0.6)
})

const capWidth = computed(() => boxWidth.value * 0.5)

const boxCenter = (index) => {
  const available = svgWidth.value - margin.left - margin.right
  const count = props.data.length || 1
  const step = available / count
  return margin.left + step * index + step / 2
}

const colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#f97316']

const boxColor = (index) => colors[index % colors.length]

const computeStats = (values) => {
  if (!values || values.length === 0) {
    return { min: 0, q1: 0, median: 0, q3: 0, max: 0, mean: 0, stdDev: 0, outliers: [] }
  }

  const sorted = [...values].sort((a, b) => a - b)
  const n = sorted.length

  const min = sorted[0]
  const max = sorted[n - 1]
  const mean = sorted.reduce((s, v) => s + v, 0) / n

  // Écart type
  const variance = sorted.reduce((s, v) => s + (v - mean) ** 2, 0) / n
  const stdDev = Math.sqrt(variance)

  const percentile = (arr, p) => {
    const idx = (p / 100) * (arr.length - 1)
    const lower = Math.floor(idx)
    const upper = Math.ceil(idx)
    if (lower === upper) return arr[lower]
    return arr[lower] + (arr[upper] - arr[lower]) * (idx - lower)
  }

  const q1 = percentile(sorted, 25)
  const median = percentile(sorted, 50)
  const q3 = percentile(sorted, 75)
  const iqr = q3 - q1

  const lowerFence = q1 - 1.5 * iqr
  const upperFence = q3 + 1.5 * iqr

  const whiskerMin = sorted.find(v => v >= lowerFence) ?? min
  const whiskerMax = [...sorted].reverse().find(v => v <= upperFence) ?? max

  const outliers = sorted.filter(v => v < lowerFence || v > upperFence)

  return { min: whiskerMin, q1, median, q3, max: whiskerMax, mean, stdDev, outliers }
}

// Add stats to data
const processedData = computed(() => {
  return props.data.map(item => ({
    ...item,
    stats: computeStats(item.values)
  }))
})

const meanDiamondPoints = (index, mean) => {
  const cx = boxCenter(index)
  const cy = yScale(mean)
  const s = 4
  return `${cx},${cy - s} ${cx + s},${cy} ${cx},${cy + s} ${cx - s},${cy}`
}

// Tooltip
const tooltip = reactive({
  visible: false,
  x: 0,
  y: 0,
  label: '',
  mean: '',
  stdDev: '',
  min: '',
  max: '',
  q1: '',
  q3: '',
  median: '',
  count: 0
})

const fmt = (v) => v.toFixed(2)

function showTooltip(index, event) {
  const item = processedData.value[index]
  if (!item) return

  const rect = container.value.getBoundingClientRect()
  const svgRect = container.value.querySelector('svg').getBoundingClientRect()

  tooltip.label = item.label
  tooltip.mean = fmt(item.stats.mean)
  tooltip.stdDev = fmt(item.stats.stdDev)
  tooltip.min = fmt(item.stats.min)
  tooltip.max = fmt(item.stats.max)
  tooltip.q1 = fmt(item.stats.q1)
  tooltip.q3 = fmt(item.stats.q3)
  tooltip.median = fmt(item.stats.median)
  tooltip.count = item.values ? item.values.length : 0

  // Position relative to container
  let x = event.clientX - rect.left + 15
  let y = event.clientY - rect.top - 10

  // Keep tooltip within container bounds
  if (x + 180 > rect.width) {
    x = event.clientX - rect.left - 185
  }
  if (y + 220 > rect.height) {
    y = rect.height - 230
  }
  if (y < 0) y = 5

  tooltip.x = x
  tooltip.y = y
  tooltip.visible = true
}

function hideTooltip() {
  tooltip.visible = false
}
</script>

<style scoped>
.boxplot-container {
  width: 100%;
  overflow-x: auto;
  position: relative;
}

.axis-label {
  font-size: 10px;
  fill: var(--text-muted, #999);
}

.grid-line {
  stroke: var(--border-color, #e5e7eb);
  stroke-dasharray: 2,2;
  stroke-width: 0.5;
}

.axis-line {
  stroke: var(--border-color, #e5e7eb);
  stroke-width: 1;
}

.whisker {
  stroke: #6b7280;
  stroke-width: 1.5;
}

.box {
  rx: 3;
  ry: 3;
}

.median-line {
  stroke-width: 2.5;
}

.mean-marker {
  fill: #ef4444;
  stroke: none;
}

.outlier {
  fill: #ef4444;
  stroke: #ef4444;
  stroke-width: 1;
}

.x-label {
  font-size: 10px;
  fill: var(--text-muted, #999);
}

.value-label {
  font-size: 9px;
  fill: var(--text-primary, #333);
  font-weight: 600;
}

/* Tooltip */
.boxplot-tooltip {
  position: absolute;
  background: var(--bg-primary, #fff);
  border: 1px solid var(--border-color, #e5e7eb);
  border-radius: 8px;
  padding: 10px 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  pointer-events: none;
  z-index: 100;
  min-width: 170px;
  font-size: 12px;
}

.tooltip-title {
  font-weight: 700;
  font-size: 13px;
  color: var(--text-primary, #333);
  margin-bottom: 6px;
  padding-bottom: 4px;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
}

.tooltip-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 2px 0;
  color: var(--text-secondary, #555);
  gap: 6px;
}

.tooltip-icon {
  display: inline-block;
  width: 18px;
  font-size: 10px;
  font-weight: 700;
  color: var(--text-muted, #999);
  text-align: center;
}

.tooltip-value {
  font-weight: 600;
  color: var(--text-primary, #333);
  font-variant-numeric: tabular-nums;
}

.tooltip-divider {
  height: 1px;
  background: var(--border-color, #e5e7eb);
  margin: 3px 0;
}

.tooltip-count {
  margin-top: 4px;
  padding-top: 4px;
  border-top: 1px solid var(--border-color, #e5e7eb);
  font-size: 11px;
  color: var(--text-muted, #999);
  justify-content: center;
}
</style>