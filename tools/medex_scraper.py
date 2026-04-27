import requests
from bs4 import BeautifulSoup
import re
import os

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
OUTPUT_FILE = os.path.join(SCRIPT_DIR, "seed_data.sql")

# The Complete 10x4 Dataset (40 Brands)
medications = [
    # 1. Paracetamol
    {"generic": "Paracetamol", "brand": "Napa", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/10452/napa-500-mg-tablet", "e": 5.0, "p": 5.0, "pop": 5.0},
    {"generic": "Paracetamol", "brand": "Ace", "manufacturer": "Square", "url": "https://medex.com.bd/brands/10377/ace-500-mg-tablet", "e": 5.0, "p": 5.0, "pop": 4.8},
    {"generic": "Paracetamol", "brand": "Fast", "manufacturer": "Acme", "url": "https://medex.com.bd/brands/5017/fast-500-mg-tablet", "e": 4.5, "p": 5.0, "pop": 4.5},
    {"generic": "Paracetamol", "brand": "Reset", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/7915/reset-500-mg-tablet", "e": 4.5, "p": 5.0, "pop": 4.2},
    
    # 2. Amoxicillin
    {"generic": "Amoxicillin Trihydrate", "brand": "Moxacil", "manufacturer": "Square", "url": "https://medex.com.bd/brands/6570/moxacil-500-mg-capsule", "e": 5.0, "p": 4.0, "pop": 5.0},
    {"generic": "Amoxicillin Trihydrate", "brand": "Fimox", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/6241/fimox-500-mg-capsule", "e": 4.8, "p": 4.5, "pop": 4.5},
    {"generic": "Amoxicillin Trihydrate", "brand": "Sinex", "manufacturer": "Opsonin", "url": "https://medex.com.bd/brands/7643/sinex-500-mg-capsule", "e": 4.5, "p": 4.8, "pop": 4.0},
    {"generic": "Amoxicillin Trihydrate", "brand": "Tycil", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/1516/tycil-500-mg-capsule", "e": 4.5, "p": 4.5, "pop": 4.2},

    # 3. Omeprazole
    {"generic": "Omeprazole", "brand": "Seclo", "manufacturer": "Square", "url": "https://medex.com.bd/brands/1958/seclo-20-mg-capsule", "e": 5.0, "p": 4.0, "pop": 5.0},
    {"generic": "Omeprazole", "brand": "Losectil", "manufacturer": "Eskayef", "url": "https://medex.com.bd/brands/1827/losectil-20-mg-capsule", "e": 4.8, "p": 4.2, "pop": 4.7},
    {"generic": "Omeprazole", "brand": "Progut", "manufacturer": "Renata", "url": "https://medex.com.bd/brands/7619/progut-20-mg-capsule", "e": 4.5, "p": 4.5, "pop": 4.2},
    {"generic": "Omeprazole", "brand": "Ometid", "manufacturer": "Opsonin", "url": "https://medex.com.bd/brands/7533/ometid-20-mg-capsule", "e": 4.5, "p": 4.8, "pop": 4.0},

    # 4. Metformin
    {"generic": "Metformin Hydrochloride", "brand": "Comet", "manufacturer": "Square", "url": "https://medex.com.bd/brands/3910/comet-500-mg-tablet", "e": 5.0, "p": 5.0, "pop": 5.0},
    {"generic": "Metformin Hydrochloride", "brand": "Daomin", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/3931/daomin-500-mg-tablet", "e": 4.5, "p": 5.0, "pop": 4.5},
    {"generic": "Metformin Hydrochloride", "brand": "Bigmet", "manufacturer": "Renata", "url": "https://medex.com.bd/brands/5357/bigmet-500-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.2},
    {"generic": "Metformin Hydrochloride", "brand": "Oramet", "manufacturer": "JMI", "url": "https://medex.com.bd/brands/8664/oramet-500-mg-tablet", "e": 4.0, "p": 4.8, "pop": 4.0},

    # 5. Losartan
    {"generic": "Losartan Potassium", "brand": "Osartil", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/2760/osartil-50-mg-tablet", "e": 5.0, "p": 4.5, "pop": 5.0},
    {"generic": "Losartan Potassium", "brand": "Angilock", "manufacturer": "Square", "url": "https://medex.com.bd/brands/2753/angilock-50-mg-tablet", "e": 4.8, "p": 4.0, "pop": 4.8},
    {"generic": "Losartan Potassium", "brand": "Losar", "manufacturer": "Opsonin", "url": "https://medex.com.bd/brands/2736/losar-50-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.3},
    {"generic": "Losartan Potassium", "brand": "Ostan", "manufacturer": "Renata", "url": "https://medex.com.bd/brands/26642/ostan-50-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.0},

    # 6. Ketorolac
    {"generic": "Ketorolac Tromethamine", "brand": "Torax", "manufacturer": "Square", "url": "https://medex.com.bd/brands/7918/torax-10-mg-tablet", "e": 5.0, "p": 4.0, "pop": 5.0},
    {"generic": "Ketorolac Tromethamine", "brand": "Rollac", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/7926/rollac-10-mg-tablet", "e": 4.8, "p": 4.5, "pop": 4.5},
    {"generic": "Ketorolac Tromethamine", "brand": "Emodol", "manufacturer": "Jayson", "url": "https://medex.com.bd/brands/15314/emodol-10-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.0},
    {"generic": "Ketorolac Tromethamine", "brand": "Rolac", "manufacturer": "Renata", "url": "https://medex.com.bd/brands/7919/rolac-10-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.0},

    # 7. Salbutamol
    {"generic": "Salbutamol", "brand": "Windel", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/11566/windel-4-mg-tablet", "e": 5.0, "p": 5.0, "pop": 5.0},
    {"generic": "Salbutamol", "brand": "Sulolin", "manufacturer": "Square", "url": "https://medex.com.bd/brands/11561/sulolin-4-mg-tablet", "e": 4.8, "p": 4.8, "pop": 4.5},
    {"generic": "Salbutamol", "brand": "Brodil", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/11559/brodil-4-mg-tablet", "e": 4.5, "p": 4.5, "pop": 4.2},
    {"generic": "Salbutamol", "brand": "Azmasol", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/3446/azmasol-100-mcg-inhaler", "e": 4.5, "p": 4.5, "pop": 4.8},

    # 8. Azithromycin
    {"generic": "Azithromycin", "brand": "Zimax", "manufacturer": "Square", "url": "https://medex.com.bd/brands/25666/zimax-500-mg-tablet", "e": 5.0, "p": 4.0, "pop": 5.0},
    {"generic": "Azithromycin", "brand": "Tridosil", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/8703/tridosil-500-mg-tablet", "e": 4.8, "p": 4.5, "pop": 4.8},
    {"generic": "Azithromycin", "brand": "Zithrin", "manufacturer": "Renata", "url": "https://medex.com.bd/brands/8733/zithrin-500-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.5},
    {"generic": "Azithromycin", "brand": "Odmon", "manufacturer": "Opsonin", "url": "https://medex.com.bd/brands/3371/odmon-500-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.0},

    # 9. Pantoprazole
    {"generic": "Pantoprazole", "brand": "Pantonix", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/3201/pantonix-40-mg-tablet", "e": 5.0, "p": 4.0, "pop": 5.0},
    {"generic": "Pantoprazole", "brand": "Protonp", "manufacturer": "Square", "url": "https://medex.com.bd/brands/3198/protonp-40-mg-tablet", "e": 4.8, "p": 4.2, "pop": 4.8},
    {"generic": "Pantoprazole", "brand": "Trupan", "manufacturer": "Opsonin", "url": "https://medex.com.bd/brands/3183/trupan-40-mg-tablet", "e": 4.5, "p": 4.5, "pop": 4.5},
    {"generic": "Pantoprazole", "brand": "Pantodac", "manufacturer": "Renata", "url": "https://medex.com.bd/brands/3195/pantodac-40-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.2},

    # 10. Atorvastatin
    {"generic": "Atorvastatin", "brand": "Lipicon", "manufacturer": "Incepta", "url": "https://medex.com.bd/brands/2696/lipicon-10-mg-tablet", "e": 5.0, "p": 4.0, "pop": 5.0},
    {"generic": "Atorvastatin", "brand": "Atova", "manufacturer": "Beximco", "url": "https://medex.com.bd/brands/1133/atova-10-mg-tablet", "e": 4.8, "p": 4.2, "pop": 4.8},
    {"generic": "Atorvastatin", "brand": "Anzitor", "manufacturer": "Square", "url": "https://medex.com.bd/brands/1118/anzitor-10-mg-tablet", "e": 4.5, "p": 4.5, "pop": 4.5},
    {"generic": "Atorvastatin", "brand": "Tigit", "manufacturer": "Opsonin", "url": "https://medex.com.bd/brands/2697/tigit-10-mg-tablet", "e": 4.5, "p": 4.8, "pop": 4.2}
]

headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}

def clean_sql_text(text):
    if not text:
        return ""
    text = text.replace("'", "''")
    return " ".join(text.split())

def scrape_medication_data(url):
    print(f"Scraping: {url}")
    try:
        response = requests.get(url, headers=headers, timeout=10)
        if response.status_code != 200:
            return None
        
        soup = BeautifulSoup(response.content, 'html.parser')
        data = {"indications": "", "interactions": "", "warnings": "", "price": 0.00}
        
        # 1. BULLETPROOF DOM TRAVERSAL: Search by Heading Text, not CSS classes
        # Medex uses h3, h4, or h5 for headings. We check them all.
        for tag in soup.find_all(['h3', 'h4', 'h5', 'div']):
            title = tag.get_text(strip=True).lower()
            
            # Check if this tag matches our target clinical sections
            if title in ['indications', 'interaction', 'precautions & warnings', 'contraindications', 'warnings']:
                
                # The clinical text is always inside the immediate next <div> sibling
                content_div = tag.find_next_sibling('div')
                if content_div:
                    text = content_div.get_text(separator=' ', strip=True)
                    
                    if 'indication' in title:
                        data['indications'] = text
                    elif 'interaction' in title:
                        data['interactions'] = text
                    elif 'precautions' in title or 'contraindication' in title or 'warning' in title:
                        # Append these together for a comprehensive GP alert profile
                        data['warnings'] += text + " "

        # 2. BULLETPROOF PRICE EXTRACTION: Grab the first Taka amount on the page
        price_match = re.search(r'৳\s*([0-9.]+)', soup.get_text())
        if price_match:
            data['price'] = float(price_match.group(1))
            
        return data
    except Exception as e:
        print(f"Error fetching {url}: {e}")
        return None

with open(OUTPUT_FILE, "w", encoding="utf-8") as sql_file:
    sql_file.write("-- Auto-generated Medex Seed Data\n\n")
    
    # FIX 1: Use a Dictionary to strictly map Generics to IDs
    processed_generics = {} 
    generic_id_counter = 1
    
    for med in medications:
        scraped_data = scrape_medication_data(med["url"])
        
        if not scraped_data:
            print(f" -> FAILED to scrape {med['brand']}")
            continue
            
        # 1. Insert Generic (Only if we haven't processed it yet)
        if med["generic"] not in processed_generics:
            ind = clean_sql_text(scraped_data["indications"])
            inter = clean_sql_text(scraped_data["interactions"])
            warn = clean_sql_text(scraped_data["warnings"])
            
            gen_sql = f"INSERT INTO Generics (GenericID, GenericName, Indications, Interactions, Warnings) VALUES ({generic_id_counter}, '{med['generic']}', '{ind}', '{inter}', '{warn}');\n"
            sql_file.write(gen_sql)
            
            # Save the exact ID mapped to this generic string
            processed_generics[med["generic"]] = generic_id_counter
            current_gen_id = generic_id_counter
            generic_id_counter += 1
        else:
            # Look up the exact ID from the dictionary
            current_gen_id = processed_generics[med["generic"]]

        # 2. Insert Brand
        b_name = clean_sql_text(med['brand'])
        mfg = clean_sql_text(med['manufacturer'])
        price = scraped_data["price"]
        
        brand_sql = f"INSERT INTO Brands (GenericID, BrandName, Manufacturer, UnitPrice, EfficacyRating, PriceRating, PopularityRating) VALUES ({current_gen_id}, '{b_name}', '{mfg}', {price}, {med['e']}, {med['p']}, {med['pop']});\n"
        sql_file.write(brand_sql)
        
    print(f"\nSuccess! Data written to: {OUTPUT_FILE}")