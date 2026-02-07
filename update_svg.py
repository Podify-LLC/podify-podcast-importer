
import re
import os

def update_svg():
    try:
        # 1. Read the source SVG path
        with open(r'e:\_Workproject\__PODIFY\custom plugins\podify-podcast-importer-pro\scrubbar player', 'r', encoding='utf-8') as f:
            content = f.read()
            
        # Extract d="..."
        # Looking for d="M..." inside a path tag
        match = re.search(r'<path[^>]*d="([^"]+)"', content)
        if not match:
            print("Error: Could not find path d attribute")
            # Try looser search
            match = re.search(r'd="([^"]+)"', content)
            if match:
                 print(f"Found looser match: {match.group(1)[:50]}...")
            return
            
        new_path_data = match.group(1)
        print(f"Found path data (length: {len(new_path_data)})")
        print(f"Start: {new_path_data[:50]}...")

        # 2. Read the target PHP file
        php_path = r'e:\_Workproject\__PODIFY\custom plugins\podify-podcast-importer-pro\frontend\class-frontend-init.php'
        with open(php_path, 'r', encoding='utf-8') as f:
            php_content = f.read()
            
        # 3. Replace the path d attribute in the PHP file
        # There are two occurrences.
        # Pattern: <path [^>]*d="([^"]+)"
        # We want to be careful not to replace other paths (like icons).
        # The specific paths in class-frontend-init.php are:
        # 1. Inside $player_html .= '<path fill="url(#'.$grad_id.')" d="...">'
        # 2. Inside $html .= '<path id="podify-sticky-progress-path" d="...">'
        
        # Strategy: specific regex for the waveform paths
        
        # Regex for Single Player
        # $player_html .= '<path fill="url(#'.$grad_id.')" d="...">'
        # We'll look for 'd="M 0 30' start to ensure we hit the right one, or just the context
        
        # Single Player
        pattern1 = r'(<path fill="url\(#\'\.\$grad_id\.\'\)" d=")([^"]+)(")'
        
        # Sticky Player
        pattern2 = r'(<path id="podify-sticky-progress-path" d=")([^"]+)(")'
        
        # Check if patterns exist
        if not re.search(pattern1, php_content):
            print("Error: Could not find Single Player path pattern")
        if not re.search(pattern2, php_content):
            print("Error: Could not find Sticky Player path pattern")
            
        # Perform replacement
        new_php_content = re.sub(pattern1, r'\g<1>' + new_path_data + r'\g<3>', php_content)
        new_php_content = re.sub(pattern2, r'\g<1>' + new_path_data + r'\g<3>', new_php_content)
        
        if new_php_content == php_content:
            print("Warning: No changes made (content might be identical)")
        else:
            with open(php_path, 'w', encoding='utf-8') as f:
                f.write(new_php_content)
            print("Success: Updated SVG paths in class-frontend-init.php")
            
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    update_svg()
