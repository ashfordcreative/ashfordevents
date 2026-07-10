=== Ashford Events ===
Contributors: ashfordcreative
Tags: events, calendar, csv import
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.7
License: GPL-2.0-or-later

Lightweight events calendar: month/list views, per-event colors and labels, single event pages, CSV import with preview, iCal feeds, and one-click migration from The Events Calendar.

== Usage ==

1. Activate the plugin, then visit Settings > Permalinks once (or just activate — rewrites are flushed automatically).
2. Add the calendar to any page with the shortcode:

   [ashford_events]                        Month grid (collapses to a list on mobile)
   [ashford_events view="list"]            List view
   [ashford_events view="list" months="3"] List view spanning 3 months
   [ashford_events category="tournament"]  Filter by category slug

3. Import events: Events > Import & Tools > upload your CSV, review the preview, then Run Import.
   - Reimporting a corrected sheet is safe: rows matching an existing event (name + date + start time) update it.
   - A start time of "TBD" imports as an all-day event and displays "Time TBD".
   - The "Event Description" column is used as the display label under the title (or add an "Event Label" column).
   - Add an optional "Event Color" column (hex, e.g. #C9A353) to color events straight from the sheet.
   - "Event Featured Image" URLs already in the media library are matched, not re-downloaded.

4. Colors resolve in this order: event override > category color > default color (set under Events > Import & Tools).

5. Migrating from The Events Calendar: Events > Import & Tools > Migrate Events. Slugs are preserved so existing
   /event/... URLs keep working. Deactivate The Events Calendar after confirming the migration, then flush permalinks.

== Theming ==

All frontend colors are CSS custom properties. Calendar defaults to a white background with filled, colored
event cards. To adjust:

  .ash-cal {
    --ash-accent: #C9A353;   /* fallback card color            */
    --ash-bg:     #ffffff;   /* calendar background            */
    --ash-cell:   #F6F6F4;   /* day cell background            */
    --ash-radius: 10px;      /* card corner radius             */
  }

Card text color (black/white) is chosen automatically per card for contrast against its color.

Month navigation and the category filter update in place (no page reload); the URL still tracks the month
so views remain shareable and the back button works. Hovering an event on the month grid shows a preview
card with the event's featured image, date/time, and label (pointer devices only; popovers flip near
viewport edges automatically).

== Single event pages ==

Single event pages follow the classic events layout: "« All Events" back link, title, date line, featured
image, Add to Calendar dropdown (Google / .ics), a Details list (date, time, category, buy-in, guarantee,
rebuys, website), and a Related Events grid of the next three upcoming events in the same category.

Set the "« All Events" destination under Events > Import & Tools > Calendar page URL (point it at the page
holding your [ashford_events] shortcode).

The plugin ships its own single event template so page-builder single-post templates can't mangle the layout.
To customize: copy templates/single-event.php into your theme as single-ash_event.php. To disable entirely
and let your theme render events (details are then prepended to the content):
  add_filter( 'ash_events_use_template', '__return_false' );

== iCal ==

Subscribe links (Google / Apple / Outlook) render under every calendar. The feed lives at:
  https://yoursite.com/?ash_ical=1
Single events offer their own .ics download on the event page.
