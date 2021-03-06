/* jslint vars: true */
/*jslint indent: 3 */
/* global define */
'use strict';

/**
* Dealing with the reports :
* - storage in JS
* - function to access easily to information
*/

// mettre des parseInt
define(['jQuery'], function($) {
   var r = {}; //all the reports // object pour ne pas remplir le tableau de cellules vides
   var managers_list = {}; //list of the mangers //all the reports // object pour ne pas remplir le tableau de cellules vides

   function setManagers(m) {
      /**
       * Set the manager m in the managers_list
       * @param {manager (group)} m The manager to add
       */
      var id_manager;

      if(m.manager) {
         id_manager = parseInt(m.manager.id);
         if(!(id_manager in managers_list)) {
            managers_list[id_manager] = m;
         }
      }
   }

   function getAllManagers() {
      /**
       * Returns the list of the managers.
       * @return {Array of group} The list of the managers.
       */
      return managers_list;
   }

   function updateAll(new_reports_in_json, action_after_update) {
      /**
      * Updates the local data. r[[]]
      * @param {array of reports} new_reports_in_json It is a 
      * @param {function} action_after_update a function to be executed after updating the reports. can be null if not execute
      json object containing all the report
      */
      r = {};
      $.when(
         $.each(new_reports_in_json,
            function(index, a_report) {
               update(a_report);
            }
         )
      ).done( function() {
         if (action_after_update) {
            action_after_update();
         }
      });
   }

   function update(a_report){
      /**
      * Update a report
      * @param {a json reports} a_report The report to be updated. it is a
      json object containing all the information about the report
      */
      r[parseInt(a_report.id)] = a_report;
      setManagers(a_report);
   }

   function get(an_id) {
      /**
      * Gets the report of id 'an_id'
      @param {int} an_id The relative id.
      */
      return r[parseInt(an_id)];
   }

   function getAll() {
      /**
      * Gets all the reports
      */
      return r;
   }

   function eraseAll(){
      /**
      * Remove all the reports
      */
      r = {};
   }

   function erase(report_id) {
      /**
      * Remove the report with id report_id
      * @param {int} dest_id The id of the report
      */
      delete r[parseInt(report_id)];
   }

   function getStatus(statusType, report, notFoundValue) {
      /**
       * Access to the status of a report for a given type
       * @param {String} statusType The name of the status that we want to access
       * @param {Report} report The report
       * @param {AGivenData} notFoundValue The value to return if the type is not founded
       * @return {\{-1,0,1,2,3\}} The status : -1 (gray) = rejected, ... ,3 (green) resolved
       */
      var i, max;

      for(i = 0, max = report.statuses.length; i < max; i++) {
         if(report.statuses[i].t === statusType) {
            return parseInt(report.statuses[i].v);
         }
      }
      return notFoundValue;
   }


   function getManagerId(report,notFoundValue) {
      /**
       * Returns the id of the manager of a given report.
       * @param {Report} report The reort.
       * @param {String} notFoundValue The value returned if the report has no manager assigned.
       * @return {integer} The id of the manager.
       */
      if (! report.manager) {
         return notFoundValue;
      } else {
         return report.manager.id;
      }
   }

   function isInitialized() {
      return Object.getOwnPropertyNames(r).length !== 0;
   }

   function getARandomReportId() {
      var rdata = Object.getOwnPropertyNames(r);
      return rdata[Math.floor(Math.random() * rdata.length)];
   }

   return {
      updateAll: updateAll,
      update: update,
      get: get,
      getAll: getAll,
      eraseAll: eraseAll,
      erase: erase,
      getStatus: getStatus,
      getAllManagers: getAllManagers,
      getManagerId: getManagerId,
      isInitialized: isInitialized,
      getARandomReportId: getARandomReportId,
   };
});
