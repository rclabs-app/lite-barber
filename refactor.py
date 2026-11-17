import os
import glob

REPLACEMENTS = [
    ('elite-barber', 'elite-agenda'),
    ('elitebarber', 'eliteagenda'),
    ('AdminBarberController', 'AdminProfessionalController'),
    ('BarberController', 'ProfessionalController'),
    ('barber_id', 'professional_id'),
    ('barberId', 'professionalId'),
    ('barbers', 'professionals'),
    ('Barbers', 'Professionals'),
    ('barber', 'professional'),
    ('Barber', 'Professional'),
    ('BARBER', 'PROFESSIONAL'),
]

directories_to_scan = [
    'lite-barber-app/src',
    'lite-barber-api/app',
    'lite-barber-api/routes',
    'lite-barber-api/database'
]

renames = [
    ('lite-barber-api/app/Models/Barber.php', 'lite-barber-api/app/Models/Professional.php'),
    ('lite-barber-api/app/Http/Controllers/AdminBarberController.php', 'lite-barber-api/app/Http/Controllers/AdminProfessionalController.php')
]

for src, dst in renames:
    if os.path.exists(src):
        os.rename(src, dst)

migrations = glob.glob('lite-barber-api/database/migrations/*_create_barbers_table.php')
for m in migrations:
    new_m = m.replace('create_barbers_table', 'create_professionals_table')
    os.rename(m, new_m)

for d in directories_to_scan:
    for root, dirs, files in os.walk(d):
        for file in files:
            if file.endswith(('.php', '.vue', '.js', '.json')):
                path = os.path.join(root, file)
                with open(path, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                new_content = content
                for old, new in REPLACEMENTS:
                    new_content = new_content.replace(old, new)
                
                if new_content != content:
                    with open(path, 'w', encoding='utf-8') as f:
                        f.write(new_content)

print("Refactoring complete.")
