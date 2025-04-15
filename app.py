import os
import sqlite3
import json
import numpy as np
import mne
import traceback
from datetime import datetime, timedelta
from functools import wraps
from flask import Flask, render_template, request, redirect, url_for, session, flash, jsonify, send_from_directory
from flask_wtf import FlaskForm
from flask_wtf.csrf import CSRFProtect
from wtforms import FileField, SubmitField
from wtforms.validators import DataRequired
from werkzeug.security import generate_password_hash, check_password_hash
from werkzeug.utils import secure_filename
from tensorflow.keras.models import load_model
import tensorflow as tf
import pandas as pd

# Initialize Flask app
app = Flask(__name__)
app.secret_key = 'your-secret-key-here'
app.config['UPLOAD_FOLDER'] = 'uploads'
app.config['DATABASE'] = 'user_auth.db'
app.config['ALLOWED_EXTENSIONS'] = {'csv', 'txt', 'set', 'npz'}
csrf = CSRFProtect(app)
model = None

class EEGUploadForm(FlaskForm):
    eeg_file = FileField()
    submit = SubmitField('Submit')

# Database setup
def init_db():
    with sqlite3.connect(app.config['DATABASE']) as conn:
        conn.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT,
            email TEXT,
            phone TEXT,
            age INTEGER,
            gender TEXT,
            eeg_filename TEXT,
            eeg_data BLOB,
            eeg_analysis TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """)
        # Add admin user if not exists
        try:
            conn.execute(
                "INSERT INTO users (username, password, full_name, email) VALUES (?, ?, ?, ?)",
                ('admin', generate_password_hash('admin123'), 'Admin User', 'admin@mindwave.com')
            )
        except sqlite3.IntegrityError:
            pass
        conn.commit()

def get_db():
    conn = sqlite3.connect(app.config['DATABASE'])
    conn.row_factory = sqlite3.Row  # This enables dictionary-style access
    return conn

def allowed_file(filename):
    return '.' in filename and \
           filename.rsplit('.', 1)[1].lower() in app.config['ALLOWED_EXTENSIONS']

# Login required decorator
def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if not session.get('logged_in'):
            flash('Please log in to access this page.', 'error')
            return redirect(url_for('login'))
        return f(*args, **kwargs)
    return decorated_function

# Authentication routes
@app.route('/')
def index():
    return redirect(url_for('login'))

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        
        if not username or not password:
            flash('Please enter both username and password', 'error')
            return redirect(url_for('login'))
        
        conn = get_db()
        user = conn.execute(
            'SELECT * FROM users WHERE username = ?', 
            (username,)
        ).fetchone()
        conn.close()
        
        if user and check_password_hash(user['password'], password):
            session['logged_in'] = True
            session['username'] = username
            session['user_id'] = user['id']
            flash('Login successful!', 'success')
            return redirect(url_for('home'))
        else:
            flash('Invalid username or password', 'error')
    
    return render_template('login.html')

@app.route('/logout', methods=['POST'])
def logout():
    session.pop('username', None)
    return redirect(url_for('login'))

@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')  # This is the plaintext password
        confirm_password = request.form.get('confirm_password')
        
        if password != confirm_password:
            flash('Passwords do not match', 'error')
            return redirect(url_for('register'))
        
        conn = get_db()
        existing_user = conn.execute(
            'SELECT id FROM users WHERE username = ?', 
            (username,)
        ).fetchone()
        
        if existing_user:
            conn.close()
            flash('Username already exists', 'error')
            return redirect(url_for('register'))
        
        
        conn.execute(
            'INSERT INTO users (username, password, full_name, email, phone, age, gender) VALUES (?, ?, ?, ?, ?, ?, ?)',
            (username, 
             password, 
             request.form.get('full_name'),
             request.form.get('email'),
             request.form.get('phone'),
             int(request.form.get('age')),
             request.form.get('gender'))
        )
        conn.commit()
        conn.close()
        
        flash('Registration successful! Please login.', 'success')
        return redirect(url_for('login'))
    
    return render_template('register.html')

@app.route('/forgot-password', methods=['GET', 'POST'])
def forgot_password():
    if request.method == 'POST':
        username = request.form.get('username')
        email = request.form.get('email')
        
        conn = get_db()
        user = conn.execute(
            'SELECT * FROM users WHERE username = ? AND email = ?', 
            (username, email)
        ).fetchone()
        conn.close()
        
        if user:
            if is_hashed_password(user['password']):
                return render_template('forgot_password.html',
                                   show_password=True,
                                   password="[Hashed password cannot be displayed]",
                                   username=username,
                                   is_old_account=True)
            else:
                return render_template('forgot_password.html',
                                   show_password=True,
                                   password=user['password'],
                                   username=username,
                                   is_old_account=False)
        else:
            flash('No account found with that username and email combination', 'error')
    
    return render_template('forgot_password.html')

@app.route('/reset-password', methods=['GET', 'POST'])
def reset_password():
    if request.method == 'POST':
        username = request.form.get('username')
        new_password = request.form.get('new_password')
        confirm_password = request.form.get('confirm_password')
        

        if not all([username, new_password, confirm_password]):
            flash('All fields are required', 'error')
            return redirect(url_for('reset_password'))
            
        if new_password != confirm_password:
            flash('Passwords do not match', 'error')
            return redirect(url_for('reset_password'))
        

        hashed_password = generate_password_hash(new_password)
        
        conn = get_db()
        try:
            user = conn.execute(
                'SELECT id FROM users WHERE username = ?', 
                (username,)
            ).fetchone()
            
            if not user:
                flash('Username not found', 'error')
                return redirect(url_for('reset_password'))
                
            conn.execute(
                'UPDATE users SET password = ? WHERE username = ?',
                (hashed_password, username)
            )
            conn.commit()
            flash('Password updated successfully! Please login with your new password.', 'success')
            return redirect(url_for('login'))
        except Exception as e:
            conn.rollback()
            flash('An error occurred while resetting password', 'error')
            return redirect(url_for('reset_password'))
        finally:
            conn.close()
    
    return render_template('reset_password.html')

# Main application routes
@app.route('/home')
@login_required
def home():
    return render_template('homepage.html')

@app.route('/aboutus')
@login_required
def about_us():
    return render_template('aboutus.html')

@app.route('/profile')
@login_required
def profile():
    conn = get_db()
    user = conn.execute(
        'SELECT * FROM users WHERE id = ?',
        (session['user_id'],)
    ).fetchone()
    conn.close()
    return render_template('Selfprofile.html', user=user)

@app.route('/online-data')
@login_required
def online_data():
    return render_template('Onlinedata.html')

def is_hashed_password(password):
    """Check if password is hashed (starts with pbkdf2:sha256)"""
    return password.startswith('scrypt:32768:8:1$')
def migrate_to_plaintext_passwords():
    conn = get_db()
    users = conn.execute('SELECT id, username, password FROM users').fetchall()
    
    for user in users:
        if is_hashed_password(user['password']):
            print(f"Cannot convert {user['username']} - no plaintext available")
    
    conn.close()

@app.route('/chatbot')
@login_required
def chatbot():
    return render_template('Chatbot.html')

@app.route('/mydata')
@login_required
def my_data():
    return render_template('mydata.html')

# EEG data processing
@app.route('/upload-eeg', methods=['POST'])
@login_required
def upload_eeg():
    if 'eeg_file' not in request.files:
        return jsonify({'error': 'No file uploaded'}), 400
    
    file = request.files['eeg_file']
    if file.filename == '':
        return jsonify({'error': 'No selected file'}), 400
    
    if file and allowed_file(file.filename):
        filename = secure_filename(file.filename)
        file_data = file.read()
        
        conn = get_db()
        conn.execute(
            'UPDATE users SET eeg_filename = ?, eeg_data = ? WHERE id = ?',
            (filename, sqlite3.Binary(file_data), session['user_id'])
        )
        conn.commit()
        conn.close()
        return jsonify({'success': True, 'filename': filename})
    
    return jsonify({'error': 'Invalid file type'}), 400

def load_eeg_data(filename):
    try:
        data_path = os.path.join('Data', filename)
        if not os.path.exists(data_path):
            print(f"File not found: {data_path}")
            return {
                'success': False,
                'error': f'File not found: {filename}'
            }

        raw = mne.io.read_raw_eeglab(data_path, preload=True)

        raw.filter(1, 40, method='iir', iir_params=dict(order=4, ftype='butter'))
        raw.notch_filter(50, method='iir', iir_params=dict(order=4, ftype='butter'))

        data = raw.get_data() * 1e6  # Convert to microvolts
        data = data[:, ::10]
        times = raw.times[::10]
        
        print(f"Successfully loaded {filename}")
        print(f"Data shape: {data.shape}")
        print(f"Time points: {len(times)}")
        
        data_list = np.round(data[0], 3).tolist()
        times_list = np.round(times, 3).tolist()
        
        return {
            'data': data_list,
            'times': times_list,
            'success': True
        }
    except Exception as e:
        print(f"Error loading {filename}: {str(e)}")
        print(traceback.format_exc())
        return {
            'success': False,
            'error': str(e)
        }

@app.route('/get_eeg_data/<disease>/<case_number>')
@login_required
def get_eeg_data(disease, case_number):
    try:
        if disease.lower() == 'alzheimer':
            patient_file = f'alzheimer_case{case_number}_AD.set'
            control_file = f'alzheimer_case{case_number}_NC.set'
        else:  # parkinson
            patient_file = f'parkinson_case{case_number}_PD.set'
            control_file = f'parkinson_case{case_number}_NC.set'
        
        print(f"\nAttempting to load files:")
        print(f"Patient file: {patient_file}")
        print(f"Control file: {control_file}")
        
        patient_data = load_eeg_data(patient_file)
        control_data = load_eeg_data(control_file)
        
        if not patient_data['success']:
            print(f"Failed to load patient data: {patient_data.get('error')}")
        if not control_data['success']:
            print(f"Failed to load control data: {control_data.get('error')}")
        
        response = jsonify({
            'patient': patient_data,
            'control': control_data
        })
        response.headers['Cache-Control'] = 'no-cache'
        return response
        
    except Exception as e:
        print(f"Error in get_eeg_data: {str(e)}")
        print(traceback.format_exc())
        return jsonify({
            'patient': {'success': False, 'error': str(e)},
            'control': {'success': False, 'error': str(e)}
        })
    
@app.route('/get_analysis')
@login_required
def get_analysis():
    conn = get_db()
    user = conn.execute(
        'SELECT eeg_data FROM users WHERE id = ?',
        (session['user_id'],)
    ).fetchone()
    conn.close()

    if user and user['eeg_data']:
        return jsonify({
            "status": "success",
            "data": {
                "fileInfo": {"name": "user_eeg_data.csv", "size": len(user['eeg_data'])},
                "analysis": {}  
            }
        })
    else:
        return jsonify({"status": "error", "message": "No EEG data found"})

@app.route('/save_analysis', methods=['POST'])
@login_required
def save_analysis():
    data = request.get_json() 
    if not data:
        return jsonify({"status": "error", "message": "No data received"}), 400

    conn = get_db()
    try:
        conn.execute(
            'UPDATE users SET eeg_analysis = ? WHERE id = ?',
            (json.dumps(data), session['user_id'])  
        )
        conn.commit()
        return jsonify({"status": "success"})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500
    finally:
        conn.close()

def convert_set_to_csv(set_file_path):
    """
    Reads an uploaded .set file (EEGLAB format) using MNE, converts the raw EEG data 
    into a CSV file (with time samples as rows and channel names as columns),
    saves it into a 'converted' folder, and returns the CSV filename and path.
    """
    try:
        raw = mne.io.read_raw_eeglab(set_file_path, preload=True, verbose=False)
        data = raw.get_data()  # shape: (channels, times)
        data_transposed = data.T  # now (times, channels)
        df = pd.DataFrame(data_transposed, columns=raw.ch_names)
        csv_filename = os.path.basename(set_file_path).replace('.set', '.csv')
        converted_folder = 'converted'
        os.makedirs(converted_folder, exist_ok=True)
        csv_path = os.path.join(converted_folder, csv_filename)
        df.to_csv(csv_path, index=False)
        return csv_filename, csv_path
    except Exception as e:
        print("Error during conversion to CSV:", e)
        return None, None

# NEW: Route to download the converted CSV file
@app.route('/download_csv/<filename>')
@login_required
def download_csv(filename):
    return send_from_directory('converted', filename, as_attachment=True)

# --------------------------
# Modified: EEG analysis and CSV conversion route on AIAlzheimerDetection page
@app.route('/AIAlzheimerDetection', methods=['GET', 'POST'])
def ai_alzheimer_detection():
    result = None
    form = EEGUploadForm()

    conn = get_db()
    user = conn.execute(
        'SELECT * FROM users WHERE id = ?',
        (session['user_id'],)
    ).fetchone()
    conn.close()

    if request.method == 'GET':
        result = None

    if request.method == 'POST' and form.validate_on_submit():
        file = form.eeg_file.data
        if file and file.filename.endswith('.set'):
            try:
                filename = secure_filename(file.filename)
                file_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
                file.save(file_path)

                # EEG preprocessing and prediction
                raw = mne.io.read_raw_eeglab(file_path, preload=True)
                raw.filter(1, 40, method='iir', iir_params=dict(order=4, ftype='butter'))
                raw.notch_filter(50, method='iir', iir_params=dict(order=4, ftype='butter'))

                events = mne.make_fixed_length_events(raw, duration=3.0, overlap=1.5)
                epochs = mne.Epochs(raw, events, tmin=0, tmax=3.0, baseline=None, preload=True)
                data = epochs.get_data().astype(np.float32)

                # Check for invalid data before computing power
                if np.isnan(data).any() or np.isinf(data).any() or np.all(data == 0):
                    flash("EEG data is invalid or empty.", "error")
                    return render_template("AIAlzheimerDetection.html", result=None, form=form)

                power = mne.time_frequency.tfr_array_multitaper(
                    data, sfreq=256, freqs=np.arange(4, 40, 1),
                    n_cycles=2, output='power'
                )

                feat = power.mean(axis=2).transpose(0, 2, 1)[:, :768, :]
                X = np.transpose(feat[..., np.newaxis], (0, 2, 1, 3))

                # Normalize X using the same strategy as in training
                X = (X - X.mean(axis=(1, 2), keepdims=True)) / X.std(axis=(1, 2), keepdims=True)

                # Load model per request to avoid Tensor/graph mismatch
                from tensorflow.keras.models import Model

                model = load_model(
                    os.path.join("models", "alzheimers_detection_model.h5"),
                    custom_objects={'Functional': Model}
                )

                prediction = model.predict(X[:1])[0][0]

                result = {
                    'prediction': 'Alzheimer' if prediction >= 0.5 else 'Normal',
                    'confidence': round(prediction * 100, 2) if prediction >= 0.5 else round((1 - prediction) * 100, 2)
                }

                # NEW: CSV conversion of the uploaded .set file
                csv_filename, csv_file_path = convert_set_to_csv(file_path)
                if csv_filename:
                    # Create a URL for the user to download the CSV file.
                    csv_url = url_for('download_csv', filename=csv_filename)
                    result['csv_link'] = csv_url
                else:
                    result['csv_link'] = None

                # NEW: EEG data extraction for plotting
                # Re-read the uploaded file to get the continuous EEG signal for patient plotting.
                patient_raw = mne.io.read_raw_eeglab(file_path, preload=True)
                patient_raw.filter(1, 40, method='iir', iir_params=dict(order=4, ftype='butter'))
                patient_raw.notch_filter(50, method='iir', iir_params=dict(order=4, ftype='butter'))
                patient_arr = patient_raw.get_data() * 1e6
                patient_arr = patient_arr[:, ::10]
                patient_times = patient_raw.times[::10]
                result['patient_data'] = {
                    'data': np.round(patient_arr[0], 3).tolist(),
                    'times': np.round(patient_times, 3).tolist()
                }

                # Load Normal Control data from file "upload_NC.set" in the Data folder.
                control = load_eeg_data("upload_NC.set")
                if control['success']:
                    result['control_data'] = {
                        'data': control['data'],
                        'times': control['times']
                    }
                else:
                    result['control_data'] = None

                # Optional cleanup: remove uploaded .set file after processing
                try:
                    if os.path.exists(file_path):
                        os.remove(file_path)
                except Exception as e:
                    print(f"Warning: Could not delete file {file_path}: {e}")
                    return render_template("AIAlzheimerDetection.html", result=result, form=form, user=user)
                
            except Exception as e:
                import traceback
                traceback.print_exc()
                flash(f'Error during processing: {str(e)}', 'error')
                return render_template("AIAlzheimerDetection.html", result=None, form=form, user=user)
        else:
            flash("Invalid or missing EEG .set file.", "error")

    return render_template("AIAlzheimerDetection.html", result=result, form=form, user=user)

@app.route('/delete_csv/<filename>', methods=['POST'])
@csrf.exempt
def delete_csv(filename):
    try:
        csv_path = os.path.join('converted', filename)
        if os.path.exists(csv_path):
            os.remove(csv_path)
            return jsonify({'success': True})
        else:
            return jsonify({'error': 'File not found'}), 404
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/download_normal_csv')
def download_normal_csv():
    return send_from_directory('Data', 'upload_NC.csv', as_attachment=False)


if __name__ == '__main__':
    if not os.path.exists(app.config['DATABASE']):
        init_db()
    os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)
    os.makedirs('converted', exist_ok=True)
    app.run(debug=True)
