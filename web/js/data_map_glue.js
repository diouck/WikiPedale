/* jslint vars: true */
/*jslint indent: 3 */
/* global define, Routing */
'use strict';

/**
* This module is the glue between all the other modules. This leads to fundamental function
* for the application.
*/

define(
   [
      'jQuery', 'report_map', 'report', 'report_display', 'user', 'informer', 'json_string',
      'markers_filtering', 'ol', 'recent_activities'
   ],
   function(
      $, report_map, report, report_display, user, informer, json_string,
      markers_filtering, ol, recent_activities
   ) {
      var last_description_selected = null;
      var add_new_place_mode = false; // true when the user is in a mode for adding new place
      var selected_city_data = null;
      var current_map_zoom_lvl;

      function initApp(town_lon, town_lat, map_zoom_lvl, marker_id_to_display) {
         /**
         * Init the application and the map.
         * @param {float} town_lon The longitude of the town
         * @param {float} town_lat The latitude of the town
         * @param {int} marker_id_to_display The id of the marker to display (direct access). It is optional
         * (none if no marker to display)
         */
         var init = true;
         current_map_zoom_lvl = map_zoom_lvl;

         markers_filtering.initFormFor('manager');

         report_map.init(town_lon, town_lat, map_zoom_lvl, function() {
            markers_filtering.updateFormFor('manager');
            markers_filtering.displayMarkersRegardingToFiltering();
            if(add_new_place_mode) {
               report_map.unactivateMarkers();
            }

            if(init && marker_id_to_display) {
               focusOnReport(marker_id_to_display);
               init = false;
            }
         });

         if(current_map_zoom_lvl >= 13) {
            report_map.displayLayer('markers');
            report_map.hideLayer('cluster');
         } else {
            report_map.displayLayer('cluster');
            report_map.hideLayer('markers');
         }
         hideShowLateralContent();

         report_map.addClickReportEvent(focusOnReport);

         report_map.addZoomChangeEvent(function(evt) {
            current_map_zoom_lvl = evt.map.getView().getZoom();
            if(current_map_zoom_lvl >= 13) {
               report_map.displayLayer('markers');
               report_map.hideLayer('cluster');
            } else {
               report_map.displayLayer('cluster');
               report_map.hideLayer('markers');
            }
            hideShowLateralContent();
         });

         report_map.addMapMoveEndEvent(function(evt) {
            var center = ol.proj.transform(evt.map.getView().getCenter(),'EPSG:3857', 'EPSG:4326');
            var jsonUrlData = Routing.generate('zone_view_covering_point', {_format: 'json', lon: center[0], lat: center[1]});

            $.when(
               $.getJSON(jsonUrlData, function(data) {
                  if(data.results.length > 0  && ((!selected_city_data) || selected_city_data.slug != data.results[0].slug)) {
                     selected_city_data = data.results[0];
                     recent_activities.filling(selected_city_data.slug,5);
                     $('#managed_by__url').attr('href', selected_city_data.url);
                     $('#lastest_modifications_rss_link').attr('href',
                        Routing.generate('wikipedale_history_report_by_city', {_format: 'atom', citySlug: selected_city_data.slug}));
                     $('#managed_by__img').attr('src', 'img/cities/logo_' +  selected_city_data.slug + '.png');
                     $('#div__town_presentation .title').text(selected_city_data.name.toUpperCase());
                     $('#div__town_presentation .content').text(selected_city_data.description);
                     $('#csv_export_link_town').attr('href',
                        Routing.generate('wikipedale_report_list_by_city',
                           {_format: 'csv'}) + '?city=' +  selected_city_data.slug);
                     $('#csv_export_link_town').text(selected_city_data.name + ' CSV');
                  } else if (data.results.length === 0) {
                     selected_city_data = null;
                  }
                  hideShowLateralContent();
               })
            );
         });
      }

      function hideShowLateralContent() {
        /**
         * Hide or show element in the right lateral column regarding to 
         * - the zoom level of the map (cluster or marker)
         * - if a city has been selected
         * - if a point has been selected
         * @return nothing
         */
         if(current_map_zoom_lvl >= 13) {
            if(! add_new_place_mode && last_description_selected !== null ) {
               $('#div__report_description_display').show();
            }

            $('#div__add_new_description').show();
            $('#div__filter_and_export').show();
            $('#div__town_choice').hide();
            if(selected_city_data) {
               $('#div__latest_modifications').show();
               $('#div__town_presentation').show();
               $('#managed_by').show();
            } else {
               $('#div__latest_modifications').hide();
               $('#div__town_presentation').hide();
               $('#managed_by').hide();
            }
         } else {
            $('#div__add_new_description').hide();
            $('#div__filter_and_export').hide();
            $('#div__report_description_display').hide();
            $('#div__latest_modifications').hide();
            $('#div__town_presentation').hide();
            $('managed_by').hide();
            $('#div__town_choice').show();
         }
      }

      function updateDataAndMap(){
         /**
         * Update the data of the app contained in report.js and re-draw the map
         * (regarding to the updated informations)
         */
         //report.eraseAll();
         report_map.loadReportsForBBoxView(function() {
            report_display.display_regarding_to_user_role();
         });
      }

      function lastDescriptionSelectedDelete() {
         /**
         * Delete a description. The description deleted is the description
         * having its id in the private variable last_description_selected.
         * It is the last displayed description.
         */
         var json_request = json_string.delete_place(last_description_selected);
         var url_edit = Routing.generate('wikipedale_report_change', {_format: 'json'});
         $.ajax({
            type: 'POST',
            data: {entity: json_request},
            url: url_edit,
            cache: false,
            success: function(output_json) {
               if(! output_json.query.error) {
                  report_map.deleteReport(last_description_selected);
                  $('#div_report_description_display').hide();
                  last_description_selected = null;
               } else {
                  $('#span_report_description_delete_error').show();
               }
            },
            error: function() {
               $('#span_report_description_delete_error').show();
            }
         });
      }

      function modeChange() {
         /**
         * Changin the mode between 'add_new_place' and 'edit_place' / 'show_place'.
         */
         if(!add_new_place_mode) { // add_new_place
            endDrawingDetailsOnMap('edit_report');
            report_map.showDrawnFeaturesOverlay();

            $('#div_add_new_description_button').hide();
            $('#div_add_new_description_cancel_button').show();
            report_map.unactivateMarkers();
            report_map.rmClickReportEvent();
            report_map.eraseDrawnGeojsonMarker();

            report_map.addClickMapEvent(function(evt) {
               informer.map_ok(); //le croix rouge dans le formulaire nouveau point devient verte

               var position = ol.proj.transform(evt.coordinate,'EPSG:3857', 'EPSG:4326');

               $('input[name=lon]').val(position[0]);
               $('input[name=lat]').val(position[1]);

               report_map.moveMarker('new_report', position);
            });

            report_map.displayMarker('new_report');

            if(user.isRegistered()) {
               $('#div_new_report_form_user_mail').hide();
            } else {
               $('#div_new_report_form_user_mail').show();
            }
            $('#form__add_new_description').show();
            $('#div_report_description_display').hide();
         }
         else { // edit_place / show_place
            endDrawingDetailsOnMap('new_report');
            report_map.hideDrawnFeaturesOverlay();

            $('#div_add_new_description_button').show();
            $('#div_add_new_description_cancel_button').hide();

            report_map.hideMarker('new_report');

            report_map.reactivateMarkers();
            report_map.addClickReportEvent(focusOnReport);
            report_map.rmClickMapEvent();

            $('#form__add_new_description').hide();

            if(last_description_selected !== null ) {
               $('#div_report_description_display').show();
               report_map.selectMarker(last_description_selected);
               report_map.displayDrawnGeojsonMarker(last_description_selected);
            }
         }
         add_new_place_mode = ! add_new_place_mode;
      }

      function startDrawingDetailsOnMap(action) {
         /**
          * Activation of the drawing mode (to draw polygon or line on the map)
          * @param {string} This string indicates if the drawing is done during the creation
          * or during the editon of a report :
          * - 'new_report' for creation
          * - 'edit_report' for edition
          * @return nothing
          */
         $('#button_edit_lon_lat').hide();
         $('#edit_report__draw_button').hide();
         $('#div_edit_report__draw').show();
         $('#add_new_report_form__draw_details_on_map').hide();
         $('#div_add_new_description__draw').show();
         report_map.startDrawingDetailsOnMap(action);
      }

      function endDrawingDetailsOnMap(action) {
         /**
          * Stop the drawing mode (to draw polygon or line on the map)
          * @param {string} This string indicates if the drawing is done during the creation
          * or during the editon of a report :
          * - 'new_report' for creation
          * - 'edit_report' for edition
          * @return nothing
          */
         if(action === 'edit_report' && last_description_selected) {
            report_map.displayDrawnGeojsonMarker(last_description_selected);
         }

         $('#button_edit_lon_lat').show();
         $('#add_new_report_form__draw_details_on_map').show();
         $('#div_add_new_description__draw').hide();
         $('#edit_report__draw_button').show();
         $('#div_edit_report__draw').hide();
         report_map.endDrawingDetailsOnMap(action);
      }

      function eraseDrawingDetailsOnMap(action) {
         /**
          * Erase the data (line or polygon) drawn on the map during the drawing mode
          * @param {string} This string indicates if the drawing is done during the creation
          * or during the editon of a report :
          * - 'new_report' for creation
          * - 'edit_report' for edition
          * @return nothing
          */
         report_map.eraseDrawingDetailsOnMap(action);
      }

      function changeDrawModeOnMap(action) {
         /**
          * Called when the user want to change the drawing mode :
          * - either drawing polygon
          * - either drawing line
          * @param {string} This string indicates if the drawing is done during the creation
          * or during the editon of a report :
          * - 'new_report' for creation
          * - 'edit_report' for edition
          * @return nothing
          */
         report_map.changeDrawModeOnMap(action);
      }

      function focusOnReport(report_id) {
         /**
         * Function which display some data of the description on the webpage
         * and draw the marker relative to this description as selected.
         * To be executed when the user click on a marker on the global map.
         * @param {int} report_id The id of the report to display.
         */
         if (last_description_selected) {
            report_map.unselectMarker(last_description_selected);
            report_map.eraseDrawnGeojsonMarker();
         }

         report_map.selectMarker(report_id);
         last_description_selected = report_id;
         report_display.display_description_of(report_id);
         report_map.displayDrawnGeojsonMarker(report_id);
      }

      return {
         initApp: initApp,
         updateDataAndMap: updateDataAndMap,
         lastDescriptionSelectedDelete: lastDescriptionSelectedDelete,
         modeChange: modeChange,
         focusOnReport: focusOnReport,
         startDrawingDetailsOnMap: startDrawingDetailsOnMap,
         endDrawingDetailsOnMap: endDrawingDetailsOnMap,
         eraseDrawingDetailsOnMap: eraseDrawingDetailsOnMap,
         changeDrawModeOnMap: changeDrawModeOnMap,
      };
   }
);
