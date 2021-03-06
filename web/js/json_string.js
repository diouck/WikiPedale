/* jslint vars: true */
/*jslint indent: 3 */
/* global define */
'use strict';

define(['report_map','user'], function(report_map,user) {
   function unregister_user(label,email,phonenumber){
      /**
      * Returns a json string describing an unregister user.
      * @param{string} label The label/pseudo of the user.
      * @param{string} email The email of the user.
      */
      return '{"entity":"user"' +
         ',"id":null' +
         ',"label":' + JSON.stringify(label) +
         ',"email":' + JSON.stringify(email) +
         ',"phonenumber":' + JSON.stringify(phonenumber) +
         '}';
   }

   function point(lon,lat){
      /**
      * Returns a json string describing a point.
      * @param{string} lon The longitude of the point.
      * @param{string} lat} The latitude of the point.
      */
      return '{"type":"Point","coordinates":[' + lon + ',' + lat + ']}';
   }

   function change_place(id, changement){
      /**
      * Returns a json string describing a place.
      * @param{int} id The id of the report.
      * @param{string} changement A json string representing the changement to do.
      */
      var ret = '{"entity":"report"';
      ret = ret + ',"id":' + JSON.stringify(id) + ',';
      ret = ret + changement;
      return ret + '}';
   }

   function edit_moderator_comment(id,new_moderator_comment){
      /**
      * Returns a json for editing the moderator comment of a place.
      * @param{int} id The id of the report.
      * @param{string} new_moderator_comment The new moderator comment.
      */
      return change_place(id,'"moderatorComment":' + JSON.stringify(new_moderator_comment));
   }

   function edit_description(id,new_description){
      /**
      * Returns a json for editing the parameter 'description' of a place.
      * @param{int} id The id of the report.
      * @param{string} new_description The new value of the parameter 'description'.
      */
      return change_place(id,'"description":' + JSON.stringify(new_description));
   }

   function edit_location(id,new_location){
      /**
      * Returns a json for editing location of a place.
      * @param{int} id The id of the report.
      * @param{string} new_location The new location.
      */
      return change_place(id,'"addressParts":{"entity":"address","road":' + JSON.stringify(new_location) + '}');
   }

   function edit_category(id, new_category_id){
      /**
      * Returns a json for editing the category (single) of a place.
      * @param{int} id The id of the report.
      * @param{int} new_category_id The new category id.
      */
      return change_place(id,'"category":{"entity":"category","id":' + new_category_id + '}');
   }

   function edit_status(id,status_type,new_status_value){
      /**
      * Returns a json for editing the status of a place.
      * @param{int} id The id of the report.
      * @param{string} status_type The type of the status
      * @param{string} new_status_value The new value of the status.
      */
      return change_place(id,'"statuses":[{"t":"' + status_type + '","v":"' + new_status_value + '"}]');
   }

   function edit_manager(id,new_manager_id){
      /**
      * Returns a json for editing the manager of a place.
      * @param{int} id The id of the report.
      * @param{int} new_manager_id The id of the new manager.
      */
      return change_place(id,'"manager": {"entity":"group","type":"MANAGER","id":' +
         JSON.stringify(new_manager_id)  + '}');
   }

   function edit_place_type(id, new_placetype_id){
      /**
      * Returns a json for editing place type of a place.
      * @param{int} id The id of the report.
      * @param{int} new_placetype_id The new id of the place type.
      */
      return change_place(id,'"placetype":{"id":' +  JSON.stringify(new_placetype_id) + ',"entity":"placetype"}');
   }

   function edit_place_position(id,lon,lat) {
      /**
      * Returns a json for editing the position of a place.
      * @param{int} id The id of the report.
      * @param{int} lon the new longitude of the place.
      * @param{int} lat the new latitude of the place.
      */
      return change_place(id,'"geom":'+ point(lon,lat));
   }


   function editReportDrawings(id, drawn_geojson) {
      /**
      * Returns a json for the drawings of a report.
      * @param{int} id The id of the report.
      * @param{object} drawn_geojson The geojson of the drawings.
      */
      return change_place(id, '"drawnGeoJSON":' + JSON.stringify(drawn_geojson));
   }

   function delete_place(id){
      /**
      * Returns a json for deleting a report.
      * @param{int} id The id of the report to delete.
      */
      return change_place(id,'"accepted":false');
   }

   function edit_place(description, lon, lat, address, id, color, user_label, user_email, user_phonenumber, category, drawn_geojson) {
      /**
      * Returns a json string used for adding/editing a new report.
      * @param {string} description the description of the new place.
      * @param {string} lon The longitude of the new place.
      * @param {string} lat The latitude of the new place.
      * @param {string} address The address of the new place.
      * @param {string} id The id of the new place, this parameter is optionnal : if it isn't given or null it means tha the place is a new placa.
      * @param {string} color The color of the place (only for existing place)
      * @param {string} user_label The label given by the user : if the user is register and logged this field is not considered
      * @param {string} user_email The email given by the user : if the user is register and logged this field is not considered
      * @param {string} user_phonenumber The phonenumber given by the user : if the user is register and logged this field is not considered
      * @param {int} caterogy The id of the selected category
      */
      var ret = '{"entity":"report"';

      if (typeof id === 'undefined' || id===null) {
         ret = ret + ',"id":null';
      } else {
         ret = ret + ',"id":' + JSON.stringify(id);
      }

      if (typeof lon !== 'undefined' && lon!==null && typeof lat !== 'undefined' && lat!==null) {
         ret = ret + ',"geom":'+ point(lon,lat);
      }

      if ( !user.isRegistered() && (typeof user_label !== 'undefined' || typeof user_email !== 'undefined')) {
         ret = ret + ',"creator":' + unregister_user(user_label, user_email, user_phonenumber);
      }

      ret = ret + ',"description":' + JSON.stringify(description) +
         ',"addressParts":{"entity":"address","road":' + JSON.stringify(address) + '}';

      ret = ret + ',"drawnGeoJSON":' + JSON.stringify(drawn_geojson);

      ret = ret + ',"category":{"entity":"category","id":' + category + '}';
      return ret + '}';
   }

   function moderatorManagerComment(report_id, comment_text) {
      /**
       * Return a json string use for adding/editing a new moderator-manager comment.
       * @param {int} reportId The id of the report.
       * @param {string} comment_text The text of the comment.
       * @return {sring} The json string describing the comment.
       */
      return '{"entity":"comment","reportId":' + JSON.stringify(report_id) +
      ',"text":' + JSON.stringify(comment_text) + ',"type":"moderator_manager"}';
   }

   return {
      unregister_user: unregister_user,
      change_place: change_place,
      edit_moderator_comment: edit_moderator_comment,
      edit_description: edit_description,
      edit_location: edit_location,
      edit_category: edit_category,
      edit_status: edit_status,
      edit_manager: edit_manager,
      edit_place_type: edit_place_type,
      delete_place: delete_place,
      edit_place: edit_place,
      edit_place_position: edit_place_position,
      moderatorManagerComment: moderatorManagerComment,
      editReportDrawings: editReportDrawings,
   };
});