import mysql.connector

db = mysql.connector.connect(
    host="localhost",
    user="root",
    password="",
    database="face_attendance"
)

if db.is_connected():
    print("Database berjaya disambung!")

db.close()