def init_db():
    with sqlite3.connect(app.config['DATABASE']) as conn:
        # Users table
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
        """)
        
        # EEG Analysis Results table
        conn.execute("""
        CREATE TABLE IF NOT EXISTS eeg_analysis (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            charts_data TEXT NOT NULL,
            frequency_analysis TEXT NOT NULL,
            statistical_report TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        """)
        
        # Create indexes for better performance
        conn.execute("CREATE INDEX IF NOT EXISTS idx_eeg_analysis_user ON eeg_analysis(user_id)")
        conn.execute("CREATE INDEX IF NOT EXISTS idx_eeg_analysis_created ON eeg_analysis(created_at)")
        
        # Add admin user if not exists
        try:
            conn.execute(
                "INSERT INTO users (username, password, full_name, email) VALUES (?, ?, ?, ?)",
                ('admin', 'admin123', 'Admin User', 'admin@mindwave.com')
            )
        except sqlite3.IntegrityError:
            pass
        conn.commit()