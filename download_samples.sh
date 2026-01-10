#!/bin/bash
#
# Download Sample Images for Viewer360
# These images are from Unsplash (free license)
#
# Usage: ./download_samples.sh
#

UPLOAD_DIR="$(dirname "$0")/public/uploads"

echo "📥 Downloading sample panorama images..."
echo "   Target: $UPLOAD_DIR"
echo ""

mkdir -p "$UPLOAD_DIR"

# Image sources (Unsplash - free to use)
declare -A IMAGES=(
    ["sample_mountain.jpg"]="https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=4096&q=80"
    ["sample_city.jpg"]="https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=4096&q=80"
    ["sample_beach.jpg"]="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=4096&q=80"
    ["sample_forest.jpg"]="https://images.unsplash.com/photo-1448375240586-882707db888b?w=4096&q=80"
    ["sample_desert.jpg"]="https://images.unsplash.com/photo-1509316785289-025f5b846b35?w=4096&q=80"
    ["sample_temple.jpg"]="https://images.unsplash.com/photo-1545569341-9eb8b30979d9?w=4096&q=80"
    ["sample_museum.jpg"]="https://images.unsplash.com/photo-1554907984-15263bfd63bd?w=4096&q=80"
    ["sample_aurora.jpg"]="https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=4096&q=80"
    ["sample_garden.jpg"]="https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?w=4096&q=80"
    ["sample_office_lobby.jpg"]="https://images.unsplash.com/photo-1497366216548-37526070297c?w=4096&q=80"
    ["sample_office_conf.jpg"]="https://images.unsplash.com/photo-1431540015161-0bf868a2d407?w=4096&q=80"
)

for filename in "${!IMAGES[@]}"; do
    url="${IMAGES[$filename]}"
    filepath="$UPLOAD_DIR/$filename"
    
    if [ -f "$filepath" ]; then
        echo "   ⏭️  $filename (already exists)"
    else
        echo "   ⬇️  Downloading $filename..."
        curl -sL -o "$filepath" "$url"
        if [ $? -eq 0 ]; then
            echo "      ✅ Done"
        else
            echo "      ❌ Failed"
        fi
    fi
done

echo ""
echo "✅ Sample images ready!"
echo ""
echo "📸 Image credits (Unsplash - free license):"
echo "   • Mountain: Kalen Emsley"
echo "   • City: Roberto Nickson"
echo "   • Beach: Sean Oulashin"
echo "   • Forest: Sebastian Unrau"
echo "   • Desert: Keith Hardy"
echo "   • Temple: Su San Lee"
echo "   • Museum: Michael Dziedzic"
echo "   • Aurora: Jonatan Pie"
echo "   • Garden: Eddie Kopp"
echo "   • Office: Nastuh Abootalebi & Proxyclick"
