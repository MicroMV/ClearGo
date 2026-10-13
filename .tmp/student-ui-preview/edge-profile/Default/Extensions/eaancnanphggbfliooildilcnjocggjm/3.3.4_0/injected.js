(function (xhr) {
  const XHR = XMLHttpRequest.prototype;

  const open = XHR.open;
  const send = XHR.send;
  const setRequestHeader = XHR.setRequestHeader;

  XHR.open = function (method, url) {
    this._method = method;
    this._url = url;
    this._requestHeaders = {};
    this._startTime = new Date().toISOString();
    return open.apply(this, arguments);
  };

  XHR.setRequestHeader = function (header, value) {
    this._requestHeaders[header] = value;
    return setRequestHeader.apply(this, arguments);
  };

  XHR.send = function (postData) {
    this.addEventListener('load', function () {
      const endTime = new Date().toISOString();
      const myUrl = this._url ? this._url.toLowerCase() : this._url;
      if (myUrl && (myUrl.includes('graphql') || myUrl.includes(`api/v1/feed/user`))) {
        if (
          this.responseType !== 'blob' &&
          this.responseText &&
          (this.responseText.includes('xdt_api__v1__feed__timeline__connection') || myUrl.includes(`api/v1/feed/user`))
        ) {
          try {
            let arr = JSON.parse(this.responseText || '{}');
            const s = document.createElement('script');
            s.innerHTML = JSON.stringify(arr);
            (document.head || document.documentElement).appendChild(s);
          } catch (err) {
            console.log('Error in responseType try catch');
            console.log(err);
          }
        }
      }
    });

    return send.apply(this, arguments);
  };
})(XMLHttpRequest);
