/**
 * CKEditor Inline
 */
(function ($) {
  'use strict';

  Drupal.behaviors.inlineEditor = {
    attach: function(context, settings) {
      
      // Takes in two CKEditor Node Lists containing images
      // and finds any uuid of images that are no longer
      // in the updated list (i.e. deleted)
      function diff(original, updated) {
        var deletedIds = [], ids = [], id, i;
        // Get array of ids in the updated list
        if (updated.count() > 0) {
          for (i = 0; i < updated.count(); i++) {
            id = updated.getItem(i).data('uuid');
            if (id) {
              ids.push(id);
            }
          }
        }
        // Find any ids in the original list not in the updated list
        if (original.count() > 0) {
          for (i = 0; i < original.count(); i++) {
            id = original.getItem(i).data('uuid');
            if (id && ids.indexOf(id) < 0) {
              deletedIds.push(id);
            }
          }
        }
        return deletedIds;
      }

      CKEDITOR.plugins.addExternal('imagealign', settings.pluginPath + 'imagealign/');
      CKEDITOR.plugins.addExternal('elifebutton', settings.pluginPath + 'elifebutton/');
      CKEDITOR.plugins.addExternal('embedvideo', settings.pluginPath + 'embedvideo/');
      
      var $content = $(' .node__content .field--name-body');
      var $title = $('.node__content .field--name-field-display-title');
      
      $content.attr('contenteditable', true);
      $title.attr('contenteditable', true);
      
      var uuid = false, url, data, options, node_type;
      
      // Get UUID and node type from body tag
      if ($('body').data('uuid') && $('body').data('node-type')) {
        node_type = $('body').data('node-type');
        uuid = $('body').data('uuid');
        url = '/jsonapi/node/' + node_type + '/' + uuid;
      }
      
      var bodyEditorOptions = {
        extraPlugins: 'image2,uploadimage,balloontoolbar,balloonpanel,imagealign,elifebutton,embedvideo,autoembed,pastefromword',
        toolbarGroups: [
          {"name":"basicstyles","groups":["basicstyles"]},
          {"name":"links","groups":["links"]},
          {"name":"paragraph","groups":["list","blocks"]},
          {"name":"document","groups":["mode"]},
          {"name":"insert","groups":["insert"]},
          {"name": 'styles'}
        ],
        imageUploadUrl: '/jsonapi/file/image',
        removeButtons: 'Underline,Anchor,SpecialChar,HorizontalRule,ImageAlignLeft,ImageAlignRight,ImageFullWidth,Styles',
        image2_alignClasses: [ 'align-left', 'align-center', 'align-right' ],
        image2_disableResizer: true,
        extraAllowedContent: 'elifebutton[data-href](elife-button--default,elife-button--outline);oembed(align-left,align-right,align-center);figure;figcaption;iframe[!src,width,height]',
        format_tags: 'p;h2;h3',
        embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}',
        autoEmbed_widget: 'embedVideo'
      };

      var titleEditorOptions = {
         toolbarGroups: [
          {"name":"basicstyles","groups":["basicstyles"]}
         ],
         removeButtons: 'Underline,Superscript,Subscript'
      };
      
      var ajaxOptions = {
        method: 'PATCH',
        dataType: 'json',
        accepts: {json: 'application/vnd.api+json'},
        contentType: 'application/vnd.api+json',
        url: url,
        processData: false,
        success: function(){}        
      };
      
      if (uuid) {
        var bodyEditor = $content.ckeditor(bodyEditorOptions).editor;
        var titleEditor = $title.ckeditor(titleEditorOptions).editor;
        
        bodyEditor.on( 'instanceReady', function(ck) {
          var editable = bodyEditor.editable(), images = editable.find('img');
          
          console.log(bodyEditor.filter.allowedContent);
          
          // Remove items from context menus
          bodyEditor.removeMenuItem('paste');
          //bodyEditor.removeMenuItem('cut');
          bodyEditor.removeMenuItem('copy');
          //bodyEditor.removeMenuItem('image');
          
          // Insert a figure widget when image is uploaded with fid and uuid
          bodyEditor.widgets.registered.uploadimage.onUploaded = function(upload) {
            this.replaceWith( '<figure class="image"><img src="' + upload.url + '" ' +
              'width="' + upload.responseData.width + '" ' +
              'height="' + upload.responseData.height + '" ' +
              'data-fid="' + upload.responseData.fid + '" ' +
              'data-uuid="' + upload.responseData.uuid + '">' +
              '<figcaption>Caption</figcaption></figure>');
            // force images list to be rebuilt
            images = editable.find('img');
          };
          
          // Balloon toolbar for figure/image alignment
          bodyEditor.balloonToolbars.create ({
            buttons: 'ImageAlignLeft,ImageFullWidth,ImageAlignRight',
            widgets: 'embedVideo,image'
          });
          
          // Any change in the editor contents find any deleted images
          // and remove them from the backend
          bodyEditor.on('change', function() {
            var deletedIds = diff(images, editable.find('img'));
            if (deletedIds.length > 0) {
              for (var i=0; i<deletedIds.length; i++) {
                $.ajax({
                  method: 'DELETE',
                  dataType: 'json',
                  contentType: 'application/vnd.api+json',
                  url: '/jsonapi/file/image/' + deletedIds[i]
                });
              }
            }
            images = editable.find('img');
          });
        
          // Save any changes when editor looses focus
          bodyEditor.on('blur' , function(e){
            images = editable.find('img');
            var fids = [];
            for (var i = 0; i < images.count(); i++) {
              var fid = images.getItem(i).data('fid');
              if (fid) fids.push({target_id: fid});
            }
            data = {
              data: {
                type: "node--" + node_type,
                id: uuid,
                attributes: {
                  body: {
                    value: bodyEditor.getData(),
                    format: 'basic_html'
                  },
                  field_image: fids
                }
              }            
            };
            options = $.extend({}, ajaxOptions, {data: JSON.stringify(data)});
            $.ajax(options);
          });

          // Save image in backend when receive upload request
          bodyEditor.on('fileUploadRequest', function(e) {
            var image = e.data.fileLoader.data.split(',');
            if (image[0] === 'data:image/jpeg;base64' ||
                image[0] === 'data:image/png;base64') {
              data = {
                data: {
                  type: "file--image",
                  attributes: {
                    data: image[1],
                    uri: 'public://editor-images/' + e.data.fileLoader.fileName
                  }
                }            
              };
              var xhr = e.data.fileLoader.xhr;

              xhr.setRequestHeader('Content-Type', 'application/vnd.api+json');
              xhr.setRequestHeader('Accept', 'application/vnd.api+json');
              xhr.send(JSON.stringify(data));

              // Prevent the default behavior.
              e.stop();
            } else {
              // Image format not recognised
              e.cancel();
            }
          });

          // Handle response from file save
          bodyEditor.on('fileUploadResponse', function(e) {
            // Prevent the default response handler.
            e.stop();

            // Get XHR and response.
            var data = e.data, xhr = data.fileLoader.xhr;

            if (xhr.status == 201) { 
              // New file created so set attributes so they are
              // available to the editor
              var response = JSON.parse(xhr.responseText);
              var attr = response.data.attributes;
              data.url = attr.url;
              data.fid = attr.fid;
              data.uuid = attr.uuid;
              data.width = attr.field_image_width;
              data.height = attr.field_image_height;
            } else {
              // File upload error
              e.cancel();
            }
          });

        });
        
        // Title field editor
        titleEditor.on( 'instanceReady', function(ck) {
          // Remove items from context menus
          titleEditor.removeMenuItem('paste');
          
          // Save title when title editor looses focus
          titleEditor.on('blur', function(e){
            var title = titleEditor.getData();
            data = {
              data: {
                type: "node--" + node_type,
                id: uuid,
                attributes: {
                  title: title.replace(/(<([^>]+)>)/ig,""), // strip tags
                  field_display_title: {
                    value: title,
                    format: 'basic_html'
                  }
                }
              }            
            };
            options = $.extend({}, ajaxOptions, {data: JSON.stringify(data)});
            $.ajax(options);
          });
          
        });
        
      }
      
    }  
  };
  
})(jQuery);
