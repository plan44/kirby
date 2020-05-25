import Api from "@/api/api.js";

export default {
  install(Vue, store) {

    Api.config.endpoint = Vue.prototype.$config.api;
    Api.requests = [];

    Api.config.onPrepare = (options) => {
      // if language set, add to headers
      if (store.state.languages.current) {
        options.headers["x-language"] = store.state.languages.current.code;
      }

      // add the csrf token to every request
      options.headers["x-csrf"] = window.panel.csrf;

      return options;
    };

    Api.config.onStart = (requestId, silent) => {
      if (silent === false) {
        store.dispatch("isLoading", true);
      }
      Api.requests.push(requestId);
    };

    Api.config.onComplete = (requestId) => {
      Api.requests = Api.requests.filter(value => {
        return value !== requestId;
      });

      if (Api.requests.length === 0) {
        store.dispatch("isLoading", false);
      }
    };

    Api.config.onError = error => {
      if (config.debug) {
        window.console.error(error);
      }

      // handle requests that return no auth
      if (
        error.code === 403 &&
        (error.message === "Unauthenticated" || error.key === "access.panel")
      ) {
        store.dispatch("user/logout", true);
      }
    };

    // Ping API every 5 minutes to keep session alive
    let ping = setInterval(Api.auth.user, 5 * 60 * 1000);

    Api.config.onSuccess = () => {
      clearInterval(ping);
      ping = setInterval(Api.auth.user, 5 * 60 * 1000);
    };

    Vue.prototype.$api = Api;
  }
};