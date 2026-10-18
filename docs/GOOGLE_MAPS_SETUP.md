# ChoreHive Google Maps location setup (no API key)

ChoreHive uses a Google Maps iframe preview on the customer booking form. The iframe URL updates from the manually entered address and can show saved latitude/longitude coordinates. The `Use my location` button asks the browser for permission and saves the device coordinates to the booking. Customer booking details, worker job pages, saved-address pages, and admin booking pages open stored coordinates in Google Maps.

## Why this version does not need an API key

The booking form uses a Google Maps web embed URL (`https://maps.google.com/maps?q=...&output=embed`) rather than the Google Maps JavaScript API. It does not require a Google Cloud API key or billing setup for this implementation. For Google's official Maps Embed API, note that it is also listed as no-charge with unlimited requests, but it requires a Google Cloud API key and billing account: https://developers.google.com/maps/documentation/embed/usage-and-billing

## Important limitation

This no-key iframe is a map preview, not a programmable interactive map. Because the embedded map is hosted on Google, ChoreHive cannot detect a click or dragged pin inside it. Exact coordinates can be saved using the browser's `Use my location` button, or loaded from an existing saved address; otherwise, the customer should enter a complete manual address and can open Google Maps to inspect it. The address field remains required and is the source of truth. Browser geolocation generally requires `localhost` during development or HTTPS in deployment and requires the customer to grant permission.

## Testing

1. Open ChoreHive via XAMPP at `http://localhost/...`.
2. Open a customer booking form and confirm the Google Maps preview shows Polomolok or the typed address.
3. Change the address and confirm the map preview updates.
4. Click `Use my location`, grant permission, and confirm coordinates are shown.
5. Save a test booking and confirm the client, worker, and admin views open the same saved coordinates in Google Maps.
