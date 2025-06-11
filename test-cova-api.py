#!/usr/bin/env python3
import requests
import json
import base64
import time

# API Credentials (using the same ones from your WordPress settings)
client_id = 'SeatoSky.SeatoSky'
client_secret = 'asIhtUi91ZIwPXHRnwfgMLLz'
username = 'SeatoSky.COVA.APIUser.SeatoSky'
password = 'XrE4XIU@2%'
company_id = '293892'
location_id = '293894'

# Authentication endpoint
auth_url = 'https://accounts.iqmetrix.net/v1/oauth2/token'

# Cova API base URL
api_base_url = 'https://api.covasoft.net'

def get_token():
    """Get authentication token from Cova API"""
    print("Getting authentication token...")
    
    auth_data = {
        'grant_type': 'password',
        'client_id': client_id,
        'client_secret': client_secret,
        'username': username,
        'password': password
    }
    
    response = requests.post(auth_url, data=auth_data)
    
    if response.status_code != 200:
        print(f"Error getting token: {response.status_code}")
        print(response.text)
        return None
    
    data = response.json()
    token = data.get('access_token')
    
    if not token:
        print("No token found in response")
        return None
    
    print(f"Successfully got token: {token[:10]}...")
    return token

def get_detailed_product_data(token):
    """Get detailed product data including availability"""
    print("\nGetting detailed product data...")
    
    endpoint = f"/dataplatform/v1/companies/{company_id}/DetailedProductData"
    url = f"{api_base_url}{endpoint}"
    
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
    
    # Use the exact format shown in the API documentation
    payload = {
        'LocationId': int(location_id),
        'IncludeProductSkusAndUpcs': True,
        'IncludeProductSpecifications': True,
        'IncludeClassifications': True,
        'IncludeProductAssets': True,
        'IncludeAvailability': True,
        'IncludePackageDetails': True,
        'IncludePricing': True,
        'IncludeTaxes': True,
        'InStockOnly': False,
        'IncludeAllLifecycles': True,
        'SellingRoomOnly': False,
        'Skip': 0,
        'Top': 10  # Just get a few for testing
    }
    
    print(f"Sending request to {url} with payload: {json.dumps(payload)}")
    
    response = requests.post(url, headers=headers, json=payload)
    
    if response.status_code != 200:
        print(f"Error getting product data: {response.status_code}")
        print(response.text)
        return None
    
    try:
        data = response.json()
        print(f"Successfully got product data. Products count: {len(data.get('Products', []))}")
        return data
    except json.JSONDecodeError:
        print("Error parsing JSON from response")
        print(response.text)
        return None

def get_rooms(token):
    """Get rooms for the location"""
    print("\nGetting rooms data...")
    
    endpoint = f"/v1/Companies/{company_id}/Locations/{location_id}/Rooms"
    url = f"{api_base_url}{endpoint}"
    
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json'
    }
    
    response = requests.get(url, headers=headers)
    
    if response.status_code != 200:
        print(f"Error getting rooms data: {response.status_code}")
        print(response.text)
        return None
    
    try:
        data = response.json()
        print(f"Successfully got rooms data: {len(data)} rooms")
        return data
    except json.JSONDecodeError:
        print("Error parsing JSON from response")
        print(response.text)
        return None
        
def get_room_inventory(token, room_id):
    """Get inventory for a specific room"""
    print(f"\nGetting inventory for room {room_id}...")
    
    endpoint = f"/SupplyChain/v1/companies/{company_id}/location/{location_id}/room/{room_id}/inventory"
    url = f"{api_base_url}{endpoint}"
    
    headers = {
        'Authorization': f'Bearer {token}',
        'Accept': 'application/json'
    }
    
    response = requests.get(url, headers=headers)
    
    if response.status_code != 200:
        print(f"Error getting room inventory: {response.status_code}")
        print(response.text)
        return None
    
    try:
        data = response.json()
        print(f"Successfully got inventory data for room {room_id}: {len(data)} items")
        return data
    except json.JSONDecodeError:
        print("Error parsing JSON from response")
        print(response.text)
        return None

def analyze_availability_data(products_data):
    """Analyze availability data in products"""
    print("\nAnalyzing availability data...")
    
    if not products_data or 'Products' not in products_data:
        print("No products data found")
        return
    
    products = products_data['Products']
    products_with_availability = 0
    total_availability_items = 0
    
    # Print summary of products with availability
    for product in products:
        if 'Availability' in product and product['Availability']:
            products_with_availability += 1
            total_availability_items += len(product['Availability'])
    
    print(f"Found {products_with_availability} products with availability data (out of {len(products)} products)")
    print(f"Total availability items: {total_availability_items}")
    
    # Print details of the first few products with availability
    print("\nDetailed availability data for first 3 products with availability:")
    count = 0
    for product in products:
        if 'Availability' in product and product['Availability']:
            product_id = product.get('ProductId', 'Unknown')
            name = product.get('Name', 'Unknown')
            
            print(f"\nProduct: {name} (ID: {product_id})")
            
            for idx, avail in enumerate(product['Availability']):
                in_stock = avail.get('InStockQuantity', 0)
                location_id = avail.get('LocationId', 'Unknown')
                room_id = avail.get('RoomId', 'Unknown')
                
                print(f"  Availability #{idx+1}:")
                print(f"    LocationId: {location_id}")
                print(f"    RoomId: {room_id}")
                print(f"    InStockQuantity: {in_stock}")
                
                # Print other fields that might be useful
                for key, value in avail.items():
                    if key not in ['LocationId', 'RoomId', 'InStockQuantity']:
                        print(f"    {key}: {value}")
            
            count += 1
            if count >= 3:
                break
    
    if count == 0:
        print("No products found with availability data!")

def get_direct_inventory(token):
    """Try different inventory endpoints"""
    print("\nTrying various inventory endpoints...")
    
    endpoints = [
        f"/SupplyChain/v1/companies/{company_id}/location/{location_id}/inventory",
        f"/DataPlatform/Inventory/v1/Companies({company_id})/Locations({location_id})/CatalogItems",
        f"/Inventory/v1/Companies/{company_id}/Inventory/Locations/{location_id}",
        f"/v1/companies/{company_id}/locations/{location_id}/inventory",
        f"/v1/Companies/{company_id}/Inventory"
    ]
    
    for endpoint in endpoints:
        url = f"{api_base_url}{endpoint}"
        print(f"\nTrying endpoint: {url}")
        
        headers = {
            'Authorization': f'Bearer {token}',
            'Accept': 'application/json'
        }
        
        response = requests.get(url, headers=headers)
        
        if response.status_code == 200:
            try:
                data = response.json()
                if isinstance(data, list):
                    item_count = len(data)
                elif isinstance(data, dict):
                    if 'Items' in data:
                        item_count = len(data['Items'])
                    elif 'Inventory' in data:
                        item_count = len(data['Inventory'])
                    else:
                        item_count = sum(1 for _ in data.values())
                else:
                    item_count = 0
                
                print(f"Success! Got {item_count} items")
                
                # Save to file
                filename = f"inventory_{endpoint.split('/')[-1]}.json"
                with open(filename, 'w') as f:
                    json.dump(data, f, indent=2)
                    print(f"Saved to {filename}")
                
                return data
            except json.JSONDecodeError:
                print(f"Error parsing JSON: {response.text[:100]}...")
        else:
            print(f"Error {response.status_code}: {response.text[:100]}...")
    
    print("All inventory endpoints failed")
    return None

def get_products_by_id_list(token, product_ids):
    """Get detailed product data by specific product IDs"""
    print(f"\nGetting detailed product data for {len(product_ids)} specific products...")
    
    endpoint = f"/dataplatform/v1/companies/{company_id}/DetailedProductData/ByProductIdList"
    url = f"{api_base_url}{endpoint}"
    
    headers = {
        'Authorization': f'Bearer {token}',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
    
    # Use the exact format shown in the API documentation
    payload = {
        'LocationId': int(location_id),
        'IncludeProductSkusAndUpcs': True,
        'IncludeProductSpecifications': True,
        'IncludeClassifications': True,
        'IncludeProductAssets': True,
        'IncludeAvailability': True,
        'IncludePackageDetails': True,
        'IncludePricing': True,
        'IncludeTaxes': True,
        'InStockOnly': False,
        'IncludeAllLifecycles': True,
        'SellingRoomOnly': False,
        'ProductIds': product_ids
    }
    
    print(f"Sending request to {url} with ProductIds: {product_ids}")
    
    response = requests.post(url, headers=headers, json=payload)
    
    if response.status_code != 200:
        print(f"Error getting product data: {response.status_code}")
        print(response.text)
        return None
    
    try:
        data = response.json()
        print(f"Successfully got product data for specific IDs. Products count: {len(data.get('Products', []))}")
        
        # Save to file
        with open('product_specific_ids_response.json', 'w') as f:
            json.dump(data, f, indent=2)
            print(f"Saved to product_specific_ids_response.json")
        
        return data
    except json.JSONDecodeError:
        print("Error parsing JSON from response")
        print(response.text)
        return None

def get_catalog_items(token):
    """Get catalog items data"""
    print("\nGetting catalog items...")
    
    # Try different catalog item endpoints
    endpoints = [
        f"/Catalog/v1/Companies({company_id})/CatalogItems",
        f"/v1/Companies/{company_id}/Catalog/Items",
        f"/v1/Companies/{company_id}/CatalogItems"
    ]
    
    for endpoint in endpoints:
        url = f"{api_base_url}{endpoint}"
        print(f"\nTrying catalog endpoint: {url}")
        
        headers = {
            'Authorization': f'Bearer {token}',
            'Accept': 'application/json'
        }
        
        response = requests.get(url, headers=headers)
        
        if response.status_code == 200:
            try:
                data = response.json()
                if isinstance(data, list):
                    item_count = len(data)
                elif isinstance(data, dict):
                    if 'Items' in data:
                        item_count = len(data['Items'])
                    elif 'CatalogItems' in data:
                        item_count = len(data['CatalogItems'])
                    else:
                        item_count = sum(1 for _ in data.values())
                else:
                    item_count = 0
                
                print(f"Success! Got {item_count} catalog items")
                
                # Look for any stock/inventory data in the first few items
                if item_count > 0:
                    if isinstance(data, list):
                        items_to_check = data[:3]
                    elif 'Items' in data:
                        items_to_check = data['Items'][:3]
                    elif 'CatalogItems' in data:
                        items_to_check = data['CatalogItems'][:3]
                    else:
                        items_to_check = []
                    
                    print("\nChecking for inventory data in catalog items:")
                    for idx, item in enumerate(items_to_check):
                        print(f"Item #{idx+1} - keys: {', '.join(item.keys())}")
                        
                        # Check for inventory-related fields
                        inventory_keys = ['Quantity', 'Stock', 'Inventory', 'QuantityOnHand', 'InStockQuantity', 'Availability']
                        for key in inventory_keys:
                            if key in item:
                                print(f"  Found inventory data: {key} = {item[key]}")
                
                # Save to file
                filename = f"catalog_{endpoint.split('/')[-1]}.json"
                with open(filename, 'w') as f:
                    json.dump(data, f, indent=2)
                    print(f"Saved to {filename}")
                
                return data
            except json.JSONDecodeError as e:
                print(f"Error parsing JSON: {str(e)}")
                print(f"Response: {response.text[:100]}...")
        else:
            print(f"Error {response.status_code}: {response.text[:100]}...")
    
    print("All catalog endpoints failed")
    return None

def main():
    # Get authentication token
    token = get_token()
    if not token:
        print("Failed to get token. Exiting.")
        return
    
    # Try to get detailed product data (including availability)
    product_data = get_detailed_product_data(token)
    
    if product_data:
        # Analyze availability data
        analyze_availability_data(product_data)
        
        # Save full response to a file for analysis
        with open('product_data_response.json', 'w') as f:
            json.dump(product_data, f, indent=2)
            print("\nSaved full response to product_data_response.json")
        
        # Extract product IDs from the response
        product_ids = []
        if 'Products' in product_data:
            for product in product_data['Products']:
                if 'ProductId' in product:
                    product_ids.append(product['ProductId'])
        
        # If we have some product IDs, try to get them again with ByProductIdList
        if product_ids:
            specific_product_data = get_products_by_id_list(token, product_ids[:5])  # Get the first 5 product IDs
            if specific_product_data:
                analyze_availability_data(specific_product_data)
    
    # Try to get catalog items which might have inventory data
    get_catalog_items(token)
    
    # Try alternative inventory endpoints
    get_direct_inventory(token)
    
    # Also try getting rooms and inventory data directly
    try:
        rooms_data = get_rooms(token)
        
        if rooms_data and len(rooms_data) > 0:
            # Try to get inventory for the first room
            first_room = rooms_data[0]
            room_id = first_room.get('Id')
            
            if room_id:
                room_inventory = get_room_inventory(token, room_id)
                
                if room_inventory:
                    # Save room inventory to a file
                    with open('room_inventory_response.json', 'w') as f:
                        json.dump(room_inventory, f, indent=2)
                        print(f"\nSaved room inventory to room_inventory_response.json")
                    
                    # Print a summary of inventory items
                    print("\nInventory summary:")
                    print(f"Found {len(room_inventory)} inventory items")
                    
                    if len(room_inventory) > 0:
                        print("\nFirst 3 inventory items:")
                        for idx, item in enumerate(room_inventory[:3]):
                            product_id = item.get('ProductId', item.get('CatalogItemId', 'Unknown'))
                            quantity = item.get('QuantityOnHand', item.get('InStockQuantity', 'Unknown'))
                            
                            print(f"Item #{idx+1}: ProductId: {product_id}, Quantity: {quantity}")
                            
                            # Print other key fields
                            for key, value in item.items():
                                if key not in ['ProductId', 'CatalogItemId', 'QuantityOnHand', 'InStockQuantity']:
                                    print(f"  {key}: {value}")
    except Exception as e:
        print(f"Error getting rooms data: {str(e)}")

if __name__ == "__main__":
    main() 