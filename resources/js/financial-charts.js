import { Chart, LineController, BarController, DoughnutController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Tooltip, Legend, Filler } from 'chart.js';

Chart.register(LineController, BarController, DoughnutController, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Tooltip, Legend, Filler);

const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
const textColor = isDark ? '#a0a0a0' : '#888780';
const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';

const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' Birr ' + ctx.parsed.y?.toLocaleString() } } },
    scales: {
        x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 } } },
        y: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 11 }, callback: v => 'Birr ' + (v >= 1000 ? (v/1000).toFixed(1)+'k' : v) } }
    }
};

function initCharts(data) {
    const { contribTrend, incomeTrend, expenseTrend, groupData, monthlyData } = data;
    const allMonths = [...new Set([...contribTrend.map(d=>d.month), ...incomeTrend.map(d=>d.month), ...expenseTrend.map(d=>d.month)])].sort((a,b)=>a-b);

    if (allMonths.length && document.getElementById('revenueTrendChart')) {
        const labels = allMonths.map(m => new Date(0, m - 1).toLocaleString('default', { month: 'short' }));
        const lookup = (arr, month) => { const f = arr.find(d=>d.month===month); return f ? f.total : 0; };
        new Chart(document.getElementById('revenueTrendChart'), {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Contributions',
                        data: allMonths.map(m => lookup(contribTrend, m)),
                        borderColor: '#378ADD',
                        backgroundColor: 'rgba(55,138,221,0.08)',
                        tension: 0.35,
                        pointRadius: 4,
                        fill: true,
                        borderWidth: 2
                    },
                    {
                        label: 'Income (Txns)',
                        data: allMonths.map(m => lookup(incomeTrend, m)),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40,167,69,0.08)',
                        tension: 0.35,
                        pointRadius: 4,
                        fill: true,
                        borderWidth: 2,
                        borderDash: [5, 3]
                    },
                    {
                        label: 'Expenses',
                        data: allMonths.map(m => lookup(expenseTrend, m)),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220,53,69,0.08)',
                        tension: 0.35,
                        pointRadius: 4,
                        fill: true,
                        borderWidth: 2,
                        borderDash: [3, 3]
                    }
                ]
            },
            options: {
                ...defaultOptions,
                plugins: {
                    legend: { display: true, position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 }, color: textColor } },
                    tooltip: { callbacks: { label: ctx => ' ' + ctx.dataset.label + ': Birr ' + ctx.parsed.y?.toLocaleString() } }
                }
            }
        });
    }

    if (groupData.length && document.getElementById('groupComparisonChart')) {
        new Chart(document.getElementById('groupComparisonChart'), {
            type: 'bar',
            data: {
                labels: groupData.map(g => g.group_name.length > 12 ? g.group_name.slice(0,12)+'…' : g.group_name),
                datasets: [{
                    label: 'Contributions',
                    data: groupData.map(g => g.total_contributions),
                    backgroundColor: groupData.map((_, i) => ['#1D9E75','#378ADD','#534AB7','#D4537E','#D85A30','#BA7517'][i % 6]),
                    borderRadius: 4,
                    borderWidth: 0
                }]
            },
            options: { ...defaultOptions, scales: { ...defaultOptions.scales, x: { ...defaultOptions.scales.x, ticks: { ...defaultOptions.scales.x.ticks, maxRotation: 30 } } } }
        });
    }

    if (monthlyData.length && document.getElementById('monthlyDistChart')) {
        const palette = ['#378ADD','#1D9E75','#534AB7','#D4537E','#D85A30','#BA7517','#639922','#B45309','#185FA5','#3B6D11','#0F6E56','#993556'];
        new Chart(document.getElementById('monthlyDistChart'), {
            type: 'doughnut',
            data: {
                labels: monthlyData.map(m => m.month_name),
                datasets: [{
                    data: monthlyData.map(m => m.total),
                    backgroundColor: monthlyData.map((_, i) => palette[i % palette.length]),
                    borderWidth: 2,
                    borderColor: isDark ? '#1a1a1a' : '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ' Birr ' + ctx.parsed?.toLocaleString() } }
                }
            }
        });
    }
}

const el = document.getElementById('financial-chart-data');
if (el) {
    try {
        initCharts(JSON.parse(el.textContent));
    } catch (e) {
        console.error('Chart initialization failed:', e);
    }
}
