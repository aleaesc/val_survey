# Valenzuela Barangays GeoJSON

Place a valid GeoJSON file named `valenzuela-barangays.geojson` in this folder.

Requirements:

-   The file should be a `FeatureCollection` of polygon or multipolygon features representing barangay boundaries in Valenzuela City.
-   Each feature should have a property for the barangay name. The map will look for one of these keys, in order: `name`, `Name`, `barangay`, `Barangay`, `BRGY`.

Tip: If you have a different property name, you can adjust the JS in `public/adminfrontend.html` (search for `getBgyName(feature)`).

Once the file is in place, the Admin "Barangay Map Analytics" will render and show tooltips on hover with metrics per barangay.
