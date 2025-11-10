#!/bin/bash

# Reset script for LocalTest translations
# This script restores all translation files to their initial state

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TRANSLATIONS_DIR="$SCRIPT_DIR/translations"

echo "🔄 Resetting LocalTest translations..."
echo ""

# Reset XLIFF (German)
if [ -f "$TRANSLATIONS_DIR/dist.messages.de.xlf" ]; then
    cp "$TRANSLATIONS_DIR/dist.messages.de.xlf" "$TRANSLATIONS_DIR/messages.de.xlf"
    echo "✓ Reset messages.de.xlf"
else
    echo "⚠ Warning: dist.messages.de.xlf not found"
fi

# Reset YAML (German) - only one translation
cat > "$TRANSLATIONS_DIR/messages.de.yaml" << 'EOF'
welcome.message: Willkommen
EOF
echo "✓ Reset messages.de.yaml"

# Reset JSON (German) - only one translation
cat > "$TRANSLATIONS_DIR/messages.de.json" << 'EOF'
{
    "welcome.message": "Willkommen"
}
EOF
echo "✓ Reset messages.de.json"

# Reset PHP (German) - only one translation
cat > "$TRANSLATIONS_DIR/messages.de.php" << 'EOF'
<?php

return [
    'welcome.message' => 'Willkommen',
];
EOF
echo "✓ Reset messages.de.php"

# Remove backup files
rm -f "$TRANSLATIONS_DIR"/*.backup 2>/dev/null
echo "✓ Removed backup files"

# Remove wrongly created .yml files (we use .yaml)
rm -f "$TRANSLATIONS_DIR"/*.yml 2>/dev/null

# Remove French files if they exist
rm -f "$TRANSLATIONS_DIR/messages.fr."* 2>/dev/null

echo ""
echo "✅ Reset complete!"
echo ""
echo "📊 Current state:"
echo "   • English files: 3 translations each (welcome, goodbye, save)"
echo "   • German files:  1 translation (welcome only)"
echo "   • Missing:       2 translations per German file (goodbye, save)"
