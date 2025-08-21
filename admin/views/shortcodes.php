<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>Tilgjengelige Shortcodes</h1>
  <table class="widefat striped">
    <thead>
      <tr>
        <th>Shortcode</th>
        <th>Beskrivelse</th>
        <th>Eksempel</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>[fastfood_summary]</td>
        <td>Sammendrag av leveringsvalg (pickup/levering), adresse, tips, tidspunkt og notat (checkout).</td>
        <td>[fastfood_summary]</td>
      </tr>
      <tr>
        <td>[fastfood_tip_option]</td>
        <td>Tipsvalg på checkout (forhåndsvalg + egendefinert).</td>
        <td>[fastfood_tip_option]</td>
      </tr>
      <tr>
        <td>[fastfood_delivery_fee]</td>
        <td>Viser beregnet leveringsgebyr-boks (oppdateres ved checkout).</td>
        <td>[fastfood_delivery_fee]</td>
      </tr>
      <tr>
        <td>[ffp_delivery_type]</td>
        <td>Radiovalg for Hent selv / Levering på checkout.</td>
        <td>[ffp_delivery_type]</td>
      </tr>
      <tr>
        <td>[ffp_delivery_when]</td>
        <td>Velg ønsket tidspunkt (ASAP/+15/+30 …) på checkout.</td>
        <td>[ffp_delivery_when]</td>
      </tr>
      <tr>
        <td>[ffp_note]</td>
        <td>Notatfelt til kjøkken/sjåfør på checkout.</td>
        <td>[ffp_note]</td>
      </tr>
      <tr>
        <td>[track_order order_id="1234"]</td>
        <td>Kundesporing: status, ETA, adresse og kontakt.</td>
        <td>[track_order order_id="1234"]</td>
      </tr>
      <tr>
        <td>[ffp_driver_portal]</td>
        <td>Driver‑portal (viser leveringer for sjåfører, inkl. logout‑knapp når innlogget).</td>
        <td>[ffp_driver_portal]</td>
      </tr>
      <tr>
        <td>[ffp_login]</td>
        <td>Frontend‑innlogging (kunde/ansatt/sjåfør). Viser ikke noe hvis allerede innlogget.</td>
        <td>[ffp_login]</td>
      </tr>
      <tr>
        <td>[ffp_logout_button redirect="/driver-portal/" label="Logg ut" class="button"]</td>
        <td>Logout‑knapp. Vises kun når bruker er innlogget. Logger ut og redirecter til <code>redirect</code>.</td>
        <td>[ffp_logout_button redirect="/driver-portal/" label="Logg ut"]</td>
      </tr>
    </tbody>
  </table>
</div>
