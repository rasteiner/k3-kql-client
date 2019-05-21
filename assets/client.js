(function(script) {
  const csrf = script.dataset.csrf;
  const endpoint = script.dataset.ep;

  function kql(strings, ...values) {
    let query = null;

    if (Array.isArray(strings)) {

      //build string
      query = strings.reduce((all, one, i) => {
        all += one;
        if (i < strings.length - 1) {
          all += `__${i}`
        }
        return all;
      }, '');

    } else {
      query = strings;
    }

    return new Promise((resolve, reject) => {
      fetch(endpoint, {
        method: 'POST',
        body: JSON.stringify({
          values: values,
          query: query,
          csrf: csrf
        }),
        headers: {
          'Content-Type': 'application/json'
        }
      }).then(result => result.json()).then(obj => {
        if(obj.status === 'error') {
          reject(obj);
        } else {
          if(obj.result) {
            resolve(obj.result);
          } else {
            resolve(obj);
          }
        }
      })
    })

  }

  window.kql = kql;

})(document.scripts[document.scripts.length - 1]);
