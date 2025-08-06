/* ---------------------------------------------------------------------
 * download.js
 *
 * Simple library to file downloading using client-side javascript
 *
 * Version 4.21
 *
 * (c) Dandavis
 *
 * LICENSE: The MIT License (MIT)
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject
 * to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * ---------------------------------------------------------------------
 * Description:
 * 
 * The download() function is used to trigger a file download from
 * JavaScript.
 * 
 * It specifies the contents and name of a new file placed in the
 * browser's download directory. The input can be a URL, String, Blob,
 * or Typed Array of data, or via a dataURL representing the file's data
 * as base64 or url-encoded string. No matter the input format,
 * download() saves a file using the specified file name and mime
 * information in the same manner as a server using a
 * Content-Disposition HTTP header.
 * 
 * --------------------------------------------------------------------
 * Changes
 * 
 * v1
 *   Landed a FF+Chrome compatible way of downloading strings to local
 *   un-named files, upgraded to use a hidden frame and optional mime
 * 
 * v2
 *   Added named files via a[download], msSaveBlob, IE (10+) support,
 *   and window.URL support for larger+faster saves than dataURLs
 * 
 * v3
 *   Added dataURL and Blob Input, bind-toggle arity, and legacy dataURL
 *   fallback was improved with force-download mime and base64 support.
 * 
 * v3.1
 *   Improved safari handling.
 * 
 * v4
 *   Adds AMD/UMD, commonJS, and plain browser support
 * 
 * v4.1
 *   Adds url download capability via solo URL argument (same
 *   domain/CORS only)
 * 
 * v4.2
 *   Adds semantic variable names, long (over 2MB) dataURL support, and
 *   hidden by default temp anchors
 * ---------------------------------------------------------------------
 */

(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    // AMD. Register as an anonymous module.
    define([], factory);
  } else if (typeof exports === 'object') {
    // Node. Does not work with strict CommonJS, but
    // only CommonJS-like environments that support module.exports,
    // like Node.
    module.exports = factory();
  } else {
    // Browser globals (root is window)
    root.download = factory();
  }
}(this, function () {

  return function download(data, strFileName, strMimeType) {

    var
      self = window, // this script is only for browsers anyway...
      defaultMime = "application/octet-stream", // this default mime also triggers iframe downloads
      mimeType = strMimeType || defaultMime,
      payload = data,
      url = !strFileName && !strMimeType && payload,
      anchor = document.createElement("a"),
      toString = function(a){return String(a);},
      myBlob = (self.Blob || self.MozBlob || self.WebKitBlob || toString),
      fileName = strFileName || "download",
      blob,
      reader
    ;
    
    myBlob = myBlob.call
      ? myBlob.bind(self)
      : Blob
    ;
    
    if (String(this)==="true") {
      // Reverse arguments, allowing
      // download.bind(true, "text/xml", "export.xml") to act as a callback
      payload=[payload, mimeType];
      mimeType=payload[0];
      payload=payload[1];
    }

    if (url && url.length< 2048) {
      // If no filename and no mime, assume a url was passed as the only
      // argument
      fileName = url.split("/").pop().split("?")[0];
      anchor.href = url; // assign href prop to temp anchor

      if (anchor.href.indexOf(url) !== -1) {
        // If the browser determines that it's a potentially valid url
        // path:
        var
          ajax=new XMLHttpRequest()
        ;

        ajax.open( "GET", url, true);
        ajax.responseType = 'blob';
        
        ajax.onload = function(e) { 
          download(e.target.response, fileName, defaultMime);
        };
        
        setTimeout(function() {
          ajax.send();
        }, 0);

        // Allows setting custom ajax headers using the return:
        return ajax;
      } // end if valid url?
    } // end if url?

    // Go ahead and download data URLs right away
    if (/^data:([\w+-]+\/[\w+.-]+)?[,;]/.test(payload)) {
    
      if (payload.length > (1024*1024*1.999) && myBlob !== toString ) {
        payload=dataUrlToBlob(payload);
        mimeType=payload.type || defaultMime;
      } else {      
         // IE10 can't do a[download], only Blobs:
         // everyone else can save dataURLs un-processed
        return navigator.msSaveBlob
          ? navigator.msSaveBlob(dataUrlToBlob(payload), fileName)
          : saver(payload)
        ; 
      }
    } else {
      // Not data url, is it a string with special needs?
      if (/([\x80-\xff])/.test(payload)) {
        var
          i=0,
          tempUiArr = new Uint8Array(payload.length),
          mx=tempUiArr.length
        ;
        
        for (i;i<mx;++i)
          tempUiArr[i] = payload.charCodeAt(i)
         ;
        
        payload = new myBlob([tempUiArr], {type: mimeType});
      }     
    }

    blob = payload instanceof myBlob
      ? payload
      : new myBlob([payload], {type: mimeType})
    ;

    function dataUrlToBlob(strUrl) {
      var
        parts = strUrl.split(/[:;,]/),
        type = parts[1],
        indexDecoder = strUrl.indexOf("charset")>0 ? 3: 2,
        decoder = parts[indexDecoder] == "base64" ? atob : decodeURIComponent,
        binData = decoder( parts.pop() ),
        mx = binData.length,
        i = 0,
        uiArr = new Uint8Array(mx)
      ;

      for (i;i<mx;++i)
        uiArr[i] = binData.charCodeAt(i)
       ;

      return new myBlob([uiArr], {type: type});
    }

    function saver(url, winMode) {

      if ('download' in anchor) {
      	// html5 A[download]
        anchor.href = url;
        anchor.setAttribute("download", fileName);
        anchor.className = "download-js-link";
        anchor.innerHTML = "downloading...";
        anchor.style.display = "none";
        anchor.addEventListener('click', function(e) {
          e.stopPropagation();
          this.removeEventListener('click', arguments.callee);
        });

        document.body.appendChild(anchor);

        setTimeout(function() {
          anchor.click();
          document.body.removeChild(anchor);

          if (winMode===true) {
          	setTimeout(function() {
          		self.URL.revokeObjectURL(anchor.href);
          	}, 250 );
          }
        }, 66);

        return true;
      }

      // Handle non-a[download] safari as best we can:
      if (/(Version)\/(\d+)\.(\d+)(?:\.(\d+))?.*Safari\//.test(navigator.userAgent)) {

        if (/^data:/.test(url))
        	url="data:"+url.replace(/^data:([\w\/\-\+]+)/, defaultMime);

        if (!window.open(url)) {
          // Popup blocked, offer direct download:
          if (confirm("Displaying New Document\n\nUse Save As... to download, then click back to return to this page.")) {
          	location.href=url;
          }
        }

        return true;
      }

      // Do iframe dataURL download (old ch+FF):
      var
        f = document.createElement("iframe")
      ;
      
      document.body.appendChild(f);

      if (!winMode && /^data:/.test(url)) {
      	// Force a mime that will download:
        url="data:"+url.replace(/^data:([\w\/\-\+]+)/, defaultMime);
      }

      f.src=url;

      setTimeout(function() {
      	document.body.removeChild(f);
      }, 333);

    } //End saver

    if (navigator.msSaveBlob) {
    	// IE10+ : (has Blob, but not a[download] or URL)
      return navigator.msSaveBlob(blob, fileName);
    }

    if (self.URL) {
    	// Simple fast and modern way using Blob and URL:
      saver(self.URL.createObjectURL(blob), true);
    } else {
      // Handle non-Blob()+non-URL browsers:
      if (typeof blob === "string" || blob.constructor===toString ) {
        try {
          return saver("data:" + mimeType + ";base64," + self.btoa(blob) );
        } catch(y) {
          return saver( "data:" + mimeType + "," + encodeURIComponent(blob) );
        }
      }

      // Blob but not URL support:
      reader=new FileReader();
      reader.onload=function(e) {
        saver(this.result);
      };

      reader.readAsDataURL(blob);
    }

    return true;
  }; /* end download() */
}));