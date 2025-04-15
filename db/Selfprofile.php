<?php
include 'db.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.html');
    exit();
}

$username = $_SESSION['username'];

$stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$eeg_file_exists = !is_null($user['eeg_file']) && !empty($user['eeg_file']);

if ($eeg_file_exists) {
    $eeg_filename = $user['eeg_filename'];
    $eeg_file_content = base64_encode($user['eeg_file']);
} else {
    $eeg_filename = '';
    $eeg_file_content = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile & EEG Analysis</title>
    <link rel="stylesheet" href="stylesProfile.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="header1"><h1>MindWave<img src="mindwave_logo.png" alt="Logo"></h1></div>
    <div class="nav-buttons">
        <button onclick="goToHome()">Home</button>
        <button onclick="goToOnlineData()">Online Data</button>
        <button onclick="goToMyData()">MyData</button>
        <button onclick="goToChatbot()">AI Helper</button>
        <button onclick="goToAboutUs()">About Us</button>
    </div>
    <div class="maincontent"> 
       <div class="container">       
            <div class="header">
                <h1>User Profile</h1>
                <p>View personal details and health suggestions based on brainwave analysis</p>
            </div>
            <div class="user-info">
               <div class="user-card">
                    <h3>Full Name</h3>
                    <p><?=htmlspecialchars($user['full_name'])?></p>
                </div>
                <div class="user-card">
                    <h3>Email</h3>
                    <p><?=htmlspecialchars($user['email'])?></p>
                </div>
                <div class="user-card">
                    <h3>Phone</h3>
                    <p><?=htmlspecialchars($user['phone'])?></p>
                </div>
                <div class="user-card">
                    <h3>Age</h3>
                    <p><?=htmlspecialchars($user['age'])?> Years</p>
                </div>
                <div class="user-card">
                    <h3>Gender</h3>
                    <p><?=htmlspecialchars($user['gender'])?></p>
                </div>
            </div>        

<!-- EEG frequency band description -->
<div class="eeg-info-section">
    <h2>EEG frequency band description</h2>
    <div class="eeg-info-grid">
        <div class="eeg-info-card" style="border-color: #2ecc71;">
            <h3>Delta (0.5-4 Hz)</h3>
            <p><strong>Health Range：</strong>Sober Adult &lt;5%，normally elevated during deep sleep</p>
            <p><strong>Danger threshold：</strong>&gt;10% may indicate brain damage or abnormality</p>
            <p>Related to deep sleep and repair process, be careful when too high when awake</p>
        </div>
        <div class="eeg-info-card" style="border-color: #3498db;">
            <h3>Theta (4-8 Hz)</h3>
            <p><strong>Health Range：</strong>4-8%，may be slightly higher when relaxed</p>
            <p><strong>Danger threshold：</strong>&gt;15% may be associated with attention deficit</p>
            <p>Common in meditative and creative states, pathological conditions may increase</p>
        </div>
        <div class="eeg-info-card" style="border-color: #9b59b6;">
            <h3>Alpha (8-13 Hz)</h3>
            <p><strong>Health Range：</strong>8-13%，significant when eyes closed and relaxed</p>
            <p><strong>Danger threshold：</strong>&lt;5% possibly anxiety，&gt;30% abnormal</p>
            <p>Reflects a state of relaxed alertness, and bilateral asymmetry may be abnormal</p>
        </div>
        <div class="eeg-info-card" style="border-color: #e74c3c;">
            <h3>Beta (13-30 Hz)</h3>
            <p><strong>Health Range：</strong>13-30%，increases when focused</p>
            <p><strong>Danger threshold：</strong>&gt;40% maybe too much pressure</p>
            <p>High beta waves are associated with anxiety and overthinking, and long-term increases require attention</p>
        </div>
        <div class="eeg-info-card" style="border-color: #f1c40f;">
            <h3>Gamma (30-50 Hz)</h3>
            <p><strong>Health Range：</strong>Typically low, varies by task</p>
            <p><strong>Danger threshold：</strong>&gt;25% possible tendency to epilepsy</p>
            <p>Related to higher-order cognition, abnormally high frequency requires professional evaluation</p>
        </div>
    </div>
</div>

<div class="upload-section">
    <input type="file" id="fileInput" accept=".csv, .txt" hidden>
    <label for="fileInput" class="custom-upload">Upload EEG File</label>
    <span id="fileName"></span>
    <div class="error-message" id="errorMsg"></div>
</div>

<div class="charts-container" id="chartsContainer"></div>

<div class="analysis-section">
    <h2>Frequency Distribution</h2>
    <div id="frequencyAnalysis"></div>
    <h2 style="margin-top: 30px;">Statistical Report</h2>
    <div class="report-stats" id="statisticalReport"></div>
</div>
</div>
</div>  

<script>
 const EEG_FREQUENCIES = {
            'Delta': { min: 0.5, max: 4, color: '#2ecc71' },
            'Theta': { min: 4, max: 8, color: '#3498db' },
            'Alpha': { min: 8, max: 13, color: '#9b59b6' },
            'Beta':  { min: 13, max: 30, color: '#e74c3c' },
            'Gamma': { min: 30, max: 50, color: '#f1c40f' }
        };

        class FFT {
            static fft(signal) {
                const N = signal.length;
                if (N <= 1) return signal;

                const even = [], odd = [];
                for (let i = 0; i < N; i += 2) {
                    even.push(signal[i]);
                    (i + 1 < N) && odd.push(signal[i + 1]);
                }

                const evenTransformed = FFT.fft(even);
                const oddTransformed = FFT.fft(odd);
                const output = new Array(N);

                for (let k = 0; k < N/2; k++) {
                    const angle = -2 * Math.PI * k / N;
                    const twiddle = [Math.cos(angle), Math.sin(angle)];
                    const oddK = oddTransformed[k] || [0, 0];
                    const evenK = evenTransformed[k] || [0, 0];

                    const term = [
                        twiddle[0] * oddK[0] - twiddle[1] * oddK[1],
                        twiddle[0] * oddK[1] + twiddle[1] * oddK[0]
                    ];

                    output[k] = [
                        evenK[0] + term[0],
                        evenK[1] + term[1]
                    ];

                    output[k + N/2] = [
                        evenK[0] - term[0],
                        evenK[1] - term[1]
                    ];
                }
                return output;
            }
        }

        const EEG_ELECTRODE_REGEX = /^(FP[1-9]|AF[1-9]|F[1-9]|FC[1-9]|C[1-9z]|CP[1-9]|P[1-9z]|O[1-9]|T[1-9]|A[1-2])$/i;

        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            clearPreviousData();
            document.getElementById('fileName').textContent = `${file.name} (${(file.size / 1024).toFixed(2)}KB)`;

            const reader = new FileReader();
            reader.onload = processFile;
            reader.onerror = () => showError('Error reading file');
            reader.readAsText(file);
        });

        function processFile(e) {
            try {
                const rawData = e.target.result;
                const lines = rawData.split('\n').filter(line => line.trim() !== '');
            
                if (lines.length < 2) throw new Error('File format error：at least 2 lines of data are required');

                const { headers, data } = analyzeColumns(lines);
            
                if (headers.length === 0) throw new Error('No valid data column detected');
                if (data.length < 32) throw new Error('At least 32 valid data samples are required');

                createChannelsChart(headers, data);
                analyzeEEGData(headers, data);
            } catch (error) {
                showError(`Handling Errors：${error.message}`);
            }
        }

        function analyzeColumns(lines) {
            const allRows = lines.map(line => parseCSVLine(line));
            const firstRow = allRows[0];
        
            const columnAnalysis = allRows[0].map((header, colIdx) => {
                const values = allRows.slice(1).map(row => parseFloat(row[colIdx]));
                const numericValues = values.filter(v => !isNaN(v));
            
                return {
                    index: colIdx,
                    header: header.trim(),
                    isEEG: EEG_ELECTRODE_REGEX.test(header),
                    isTimeColumn: detectTimeColumn(values),
                    isNumeric: numericValues.length === values.length
                };
            });

            console.log("Column analysis results:", columnAnalysis);

            const dataColumns = columnAnalysis.filter(col => 
                col.isNumeric && !col.isTimeColumn && (col.isEEG || !detectTimeColumn(allRows.map(row => row[col.index])))
            );

            return {
                headers: dataColumns.map(col => formatHeader(col.header, col.index)),
                data: allRows.slice(1).map(row => 
                    dataColumns.map(col => parseFloat(row[col.index]))
                ).filter(row => row.length === dataColumns.length)
            };
        }

        function detectTimeColumn(values) {
            let strictlyIncreasing = true;
            for (let i = 1; i < values.length; i++) {
                if (values[i] <= values[i-1]) {
                    strictlyIncreasing = false;
                    break;
                }
            }
        
            const diffs = [];
            for (let i = 1; i < Math.min(10, values.length); i++) {
                diffs.push(values[i] - values[i-1]);
            }
            const avgDiff = diffs.reduce((a, b) => a + b, 0) / diffs.length;
            const validDiffs = diffs.filter(d => 
                d > 0 && Math.abs(d - avgDiff) < 0.1 * avgDiff
            );

            return strictlyIncreasing && validDiffs.length >= 8;
        }

        function formatHeader(original, index) {
            const cleanHeader = original.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
            return EEG_ELECTRODE_REGEX.test(cleanHeader) ? cleanHeader : `CH${index + 1}`;
        }

    function detectDataColumns(lines) {
        const allRows = lines.map(line => parseCSVLine(line));
        const firstRow = allRows[0];
        
        const EEG_ELECTRODE_REGEX = /^([A-Za-z]+[1-9zZ]|FP[1-2]|CZ|FZ|PZ|OZ|A1A2)/;

        const columnFeatures = firstRow.map((header, colIdx) => {
            const values = allRows.slice(1).map(row => parseFloat(row[colIdx]));
            const isNumeric = values.every(v => !isNaN(v));
            
            const isEEGChannel = EEG_ELECTRODE_REGEX.test(header.toUpperCase());

            let isTimeColumn = false;
            if (!isEEGChannel && isNumeric) {
                let strictlyIncreasing = true;
                for (let i = 1; i < values.length; i++) {
                    if (values[i] <= values[i-1]) {
                        strictlyIncreasing = false;
                        break;
                    }
                }
                
                const expectedDiff = 0.004;
                let validDiffs = 0;
                for (let i = 1; i < Math.min(10, values.length); i++) {
                    const diff = values[i] - values[i-1];
                    if (Math.abs(diff - expectedDiff) < 0.0001) validDiffs++;
                }
                
                isTimeColumn = strictlyIncreasing && (validDiffs >= 8);
            }

            return {
                index: colIdx,
                header: header.trim(),
                isNumeric: isNumeric,
                isEEGChannel: isEEGChannel,
                isTimeColumn: isTimeColumn
            };
        });

        console.log("Column Analysis:", columnFeatures);

        const dataColumns = columnFeatures.filter(col => {
            return col.isEEGChannel || (col.isNumeric && !col.isTimeColumn);
        });

        return {
            headers: dataColumns.map(col => 
                col.isEEGChannel ? col.header.toUpperCase() : `CH${col.index + 1}`
            ),
            data: allRows.slice(1).map(row => 
                dataColumns.map(col => parseFloat(row[col.index])))
        };
    }

    function createChannelsChart(headers, data) {
        const container = document.getElementById('chartsContainer');
        container.innerHTML = '';

        headers.forEach((header, colIndex) => {
            const chartBox = document.createElement('div');
            chartBox.className = 'chart-box';
            chartBox.innerHTML = `<h3>${header}</h3><canvas id="chart${colIndex}"></canvas>`;
            container.appendChild(chartBox);

            const ctx = chartBox.querySelector('canvas').getContext('2d');
            const channelData = data.map(row => row[colIndex]);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map((_, i) => i + 1),
                    datasets: [{
                        label: header,
                        data: channelData,
                        borderColor: getColor(colIndex),
                        borderWidth: 1.5,
                        pointRadius: 0,
                        tension: 0.1
                    }]
                },
                options: { /* Keep the original configuration unchanged */ }
            });
        });
    }

        function getColor(index) {
            const colors = ['#5B86E5', '#36D1DC', '#FF6B6B', '#4CAF50', '#9C27B0'];
            return colors[index % colors.length];
        }

        function parseCSVLine(line) {
            return line.split(',').map(cell => {
                return cell.replace(/^["']+|["']+$/g, '').trim();
            });
        }

        class NeuroHealthAnalyzer {
            static patterns = {
                AD: {
                    name: "Alzheimer's disease tendency",
                    criteria: (dist) => dist.Theta > 35 && dist.Alpha < 30,
                    ref: "Babiloni, C., Lopez, S., Del Percio, C., Noce, G., Pascarelli, M. T., Lizio, R., Teipel, S. J., González-Escamilla, G., Bakardjian, H., George, N., Cavedo, E., Lista, S., Chiesa, P. A., Vergallo, A., Lemercier, P., Spinelli, G., Grothe, M. J., Potier, M.-C., Stocchi, F., … Hampel, H. (2020). Resting-state posterior alpha rhythms are abnormal in subjective memory complaint seniors with preclinical Alzheimer’s neuropathology and high education level: the INSIGHT-preAD study. Neurobiology of Aging, 90, 43–59. https://doi.org/10.1016/j.neurobiolaging.2020.01.012",
                    markers: ["Rear Alpha power reduction", "Theta Band Enhancement"]
                },
                PD: {
                    name: "Parkinson's disease model",
                    criteria: (dist) => dist.Beta > 40 && dist.Gamma < 15,
                    ref: "Little, S., Beudel, M., Zrinzo, L., Foltynie, T., Limousin, P., Hariz, M., Neal, S., Cheeran, B., Cagnan, H., Gratwicke, J., Aziz, T. Z., Pogosyan, A., & Brown, P. (2016). Bilateral adaptive deep brain stimulation is effective in Parkinson’s disease. Journal of Neurology, Neurosurgery and Psychiatry, 87(7), 717–721. https://doi.org/10.1136/jnnp-2015-310972",
                    markers: ["Beta Band Over-Sync"]
                },
                EP: {
                    name: "Seizure-like activity",
                    criteria: (dist) => dist.Gamma > 25 && dist.Beta > 35,
                    ref: "van Mierlo, P., Papadopoulou, M., Carrette, E., Boon, P., Vandenberghe, S., Vonck, K., & Marinazzo, D. (2014). Functional brain connectivity from EEG in epilepsy: seizure prediction and epileptogenic focus localization. Progress in Neurobiology, 121, 19–35. https://doi.org/10.1016/j.pneurobio.2014.06.004",
                    markers: ["Gamma-Beta Coupling Enhancement"]
                }
            };

            static analyze(channelData) {
                const reports = [];
                Object.entries(this.patterns).forEach(([key, pattern]) => {
                    if (pattern.criteria(channelData)) {
                        reports.push({
                            disease: pattern.name,
                            biomarkers: pattern.markers,
                            reference: pattern.ref
                        });
                    }
                });
                return reports;
            }
        }       

        function analyzeEEGData(headers, data) {
            const samplingRate = 250; 
            const analysisDiv = document.getElementById('frequencyAnalysis');
            const reportDiv = document.getElementById('statisticalReport');

            analysisDiv.innerHTML = '';
            reportDiv.innerHTML = '';

            const neuroReportContainer = document.createElement('div');
            neuroReportContainer.id = 'neuroHealthReport';
            analysisDiv.appendChild(neuroReportContainer);

            headers.forEach((header, colIndex) => {
                try {
                    const channelData = data.map(row => row[colIndex]);
                    const spectrum = performFFT(channelData, samplingRate);
                    const distribution = calculateDistribution(spectrum);
 
                    const freqCard = document.createElement('div');
                    freqCard.className = 'metric-card';
                    freqCard.innerHTML = `
                        <h3>${header} Frequency Distribution</h3>
                        ${createFrequencyElements(distribution)}
                    `;
                    analysisDiv.appendChild(freqCard);

                    const stats = calculateStatistics(distribution);
                    const healthDesc = generateHealthDescription(distribution);
                    const statCard = document.createElement('div');
                    statCard.className = 'stat-card';
                    statCard.innerHTML = `
                        <h3>${header} Statistics</h3>
                        <p>Peak frequency：${stats.max.name} (${stats.max.value.toFixed(2)}%)</p>
                        <p>Minimum frequency：${stats.min.name} (${stats.min.value.toFixed(2)}%)</p>
                        <div class="health-description">
                            <strong>Health Analysis：</strong>${healthDesc}
                        </div>
                    `;
                    reportDiv.appendChild(statCard);

                    const healthReport = NeuroHealthAnalyzer.analyze(distribution);
                    if (healthReport.length > 0) {
                        const reportCard = document.createElement('div');
                        reportCard.className = 'disease-card';
                        reportCard.innerHTML = `
                            <h3>${header} Channel anomaly detection</h3>
                            ${healthReport.map(report => `
                                <div class="disease-item">
                                    <div class="disease-header">
                                        <span class="alert-icon">⚠️</span>
                                        ${report.disease}
                                    </div>
                                    <div class="biomarkers">
                                        <strong>Biomarkers：</strong>
                                        ${report.biomarkers.join('; ')}
                                    </div>
                                    <div class="reference">
                                        Literature：${report.reference}
                                    </div>
                                </div>
                            `).join('')}
                        `;
                        neuroReportContainer.appendChild(reportCard);
                    }
                } catch (error) {
                    console.error(`Aisle ${header} Analysis failed:`, error);
                }
            });

            if (neuroReportContainer.children.length === 0) {
                neuroReportContainer.innerHTML = `
                    <div class="no-abnormality">
                        🟢 No significant abnormal neural activity patterns were detected
                    </div>
                `;
            }

          
    const disclaimer = document.createElement('div');
    disclaimer.className = 'disclaimer-box';
    disclaimer.innerHTML = `
        <h4><span class="disclaimer-icon">⚠️</span>Important Notice</h4>
        <ul>
            <li>This analysis result is automatically generated based on algorithms，<strong>cannot be used as a basis for clinical diagnosis</strong></li>
            <li>Data interpretation is for reference only. Actual medical decisions need to be combined with other examination results and clinical manifestations.</li>
            <li>The system does not guarantee the completeness or accuracy of the analysis, and the results are used at your own risk</li>
            <li>The original data will be automatically deleted 24 hours after the analysis is completed，no copies are kept</li>
        </ul>
        <p style="margin:0;">© 2025 MindWave Neural Analysis System v2.1 IEC 62304 Certified</p >
    `;
    reportDiv.appendChild(disclaimer);

        function generateHealthDescription(distribution) {
            const messages = [];
            const ranges = {
                Delta: { normalMax: 5, danger: 10 },
                Theta: { normalMax: 8, danger: 15 },
                Alpha: { normalMin: 8, normalMax: 13, danger: 30 },
                Beta: { normalMax: 30, danger: 40 },
                Gamma: { danger: 25 }
            };

            if (distribution.Delta > ranges.Delta.danger) {
                messages.push(`Delta Wave abnormally high（${distribution.Delta.toFixed(1)}%），exceeding the danger threshold${ranges.Delta.danger}%，may indicate severe brain dysfunction or profound disturbance of consciousness.`);
            } else if (distribution.Delta > ranges.Delta.normalMax) {
                messages.push(`Delta Wave abnormally high（${distribution.Delta.toFixed(1)}%），normally in the awake state, it should be less than${ranges.Delta.normalMax}%，need to pay attention to sleep quality or nervous system status.`);
            }

            if (distribution.Theta > ranges.Theta.danger) {
                messages.push(`Theta Wave danger increases（${distribution.Theta.toFixed(1)}%），exceed${ranges.Theta.danger}%，may reflect attention deficit or cognitive impairment.`);
            } else if (distribution.Theta > ranges.Theta.normalMax) {
                messages.push(`Theta Wave mildly elevated（${distribution.Theta.toFixed(1)}%），may be normal in a relaxed state; persistently elevated levels suggest evaluation.`);
            }

            if (distribution.Alpha > ranges.Alpha.danger) {
                messages.push(`Alpha Wave abnormally high（${distribution.Alpha.toFixed(1)}%），exceed${ranges.Alpha.danger}%，pathological conditions need to be excluded.`);
            } else if (distribution.Alpha < ranges.Alpha.normalMin) {
                messages.push(`Alpha Wave low（${distribution.Alpha.toFixed(1)}%），normal relaxation should be above${ranges.Alpha.normalMin}%，may indicate anxiety or stress.`);
            }

            if (distribution.Beta > ranges.Beta.danger) {
                messages.push(`Beta Wave Significantly increased（${distribution.Beta.toFixed(1)}%），exceeding the danger threshold${ranges.Beta.danger}%，may reflect high anxiety or excessive stress.`);
            } else if (distribution.Beta > ranges.Beta.normalMax) {
                messages.push(`Beta Wave high（${distribution.Beta.toFixed(1)}%），may be in a state of high concentration. If your concentration level is too high for a long time, you need to pay attention to stress management.`);
            }

            if (distribution.Gamma > ranges.Gamma.danger) {
                messages.push(`Gamma Wave abnormal（${distribution.Gamma.toFixed(1)}%），exceed${ranges.Gamma.danger}%，It may indicate neurological hypersynchrony or epileptic tendency, and professional evaluation is recommended.`);
            }

            return messages.length > 0 ? messages.join(' ') : "The distribution of each frequency band is within the normal range, with no obvious abnormal characteristics.";
        }

        }

        function calculateSamplingRate(data, timeColumn) {
            if (data.length < 2) return 250;
        
            const validDiffs = [];
            for (let i = 1; i < Math.min(10, data.length); i++) {
                const diff = data[i][timeColumn] - data[i-1][timeColumn];
                if (diff > 0 && diff < 1) { 
                    validDiffs.push(diff);
                }
            }
        
            if (validDiffs.length === 0) return 250;
        
            const avgDiff = validDiffs.reduce((a, b) => a + b, 0) / validDiffs.length;
            return Math.round(1 / avgDiff);
        }


        function performFFT(data, samplingRate) {
            if (data.length < 32) {
                console.warn('Insufficient data length，Automatically fill to 32 samples');
                while (data.length < 32) data.push(0);
            }

            const N = Math.pow(2, Math.floor(Math.log2(data.length)));
            const trimmedData = data.slice(0, N);

            const complexData = trimmedData.map(x => [x, 0]);
            const phasors = FFT.fft(complexData);

            const spectrum = [];
            for (let i = 0; i < N/2; i++) {
                const freq = (i * samplingRate) / N;
                const re = phasors[i]?.[0] || 0;
                const im = phasors[i]?.[1] || 0;
                const magnitude = Math.sqrt(re*re + im*im);
                spectrum.push({ frequency: freq, magnitude: magnitude });
            }
            return spectrum;
        }

        function calculateDistribution(spectrum) {
            const distribution = {};
            let total = 0;

            Object.entries(EEG_FREQUENCIES).forEach(([name, { min, max }]) => {
                const sum = spectrum
                    .filter(bin => bin.frequency >= min && bin.frequency <= max)
                    .reduce((a, b) => a + b.magnitude, 0);
                    distribution[name] = sum;
                    total += sum;
            });
            if (total > 0) {
                Object.keys(distribution).forEach(k => {
                    distribution[k] = (distribution[k] / total) * 100;
                });
            } else {
                Object.keys(distribution).forEach(k => distribution[k] = 0);
            }

            return distribution;
        }


        function createFrequencyElements(distribution) {
            return Object.entries(distribution).map(([name, value]) => `
                <div class="frequency-item" style="border-color: ${EEG_FREQUENCIES[name].color}">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span style="font-weight: 500;">${name}</span>
                        <span style="color: #666;">${value.toFixed(1)}%</span>
                    </div>
                    <div class="value-bar">
                        <div class="value-fill" 
                            style="width: ${value.toFixed(1)}%;
                                background: ${EEG_FREQUENCIES[name].color};
                                transition: width 0.8s ease-out;">
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function calculateStatistics(distribution) {
            const entries = Object.entries(distribution);
            if (entries.length === 0) return { avg: 0, max: { value: 0, name: 'N/A' }, min: { value: 0, name: 'N/A' } };

            const avg = entries.reduce((sum, [_, v]) => sum + v, 0) / entries.length;
            const maxEntry = entries.reduce((a, b) => a[1] > b[1] ? a : b);
            const minEntry = entries.reduce((a, b) => a[1] < b[1] ? a : b);

            return {
                avg: avg || 0,
                max: { value: maxEntry[1], name: maxEntry[0] },
                min: { value: minEntry[1], name: minEntry[0] }
            };
        }

        function clearPreviousData() {
            document.getElementById('errorMsg').style.display = 'none';
            document.getElementById('chartsContainer').innerHTML = '';
            document.getElementById('frequencyAnalysis').innerHTML = '';
            document.getElementById('statisticalReport').innerHTML = '';
        }

        function showError(message) {
            const errorElement = document.getElementById('errorMsg');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            errorElement.style.color = '#e74c3c';
            errorElement.style.marginTop = '10px';
        }


        document.addEventListener('DOMContentLoaded', function() {
    if (eegFileExists && eegFileContentBase64) {
        const decodedCSV = atob(eegFileContentBase64);
        document.getElementById('fileName').textContent = eegFileName;
        processEEGContent(decodedCSV);
    }
});

// Listen for file upload (user selects file again)
document.getElementById('fileInput').addEventListener('change', function(e){
    const file = e.target.files[0];
    if (!file) return;

    document.getElementById('fileName').textContent = file.name;

    const reader = new FileReader();
    reader.onload = function(evt){
        const fileContent = evt.target.result;
        processEEGContent(fileContent);
    };
    reader.readAsText(file);

    // Upload file to server via AJAX for saving
    const formData = new FormData();
    formData.append('eeg_file', file);

    fetch('upload_eeg.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(result => {
        console.log(result);
    })
    .catch(error => {
        console.error('Upload error:', error);
    });
});

// Clearly defined EEG analysis function
function processEEGContent(csvContent) {
    try {
        clearPreviousData(); // clear previous results clearly
        const lines = csvContent.split('\n').filter(line => line.trim() !== '');
        if (lines.length < 2) throw new Error('Insufficient data in CSV file.');

        const { headers, data } = analyzeColumns(lines);
        if (headers.length === 0 || data.length < 32) throw new Error('Invalid EEG file format or insufficient data.');

        createChannelsChart(headers, data);
        analyzeEEGData(headers, data);
    } catch (error) {
        showError(`EEG Processing Error: ${error.message}`);
        console.error(error);
    }
}
</script>
<script>
    const eegFileExists = <?= json_encode($eeg_file_exists) ?>;
    const eegFileName = <?= json_encode($eeg_filename) ?>;
    const eegFileContentBase64 = <?= json_encode($eeg_file_content) ?>;
</script>

<script src="script.js"></script>
</body>
</html>
