!function(e, t, r) {
    "use strict";
    XF.ChatCanvasPattern = XF.Element.newHandler({
        options: {
            url: "",
            isDarkPattern: !1
        },
        patternRenderer: null,
        customPattern: "",
        init() {
            this.renderCanvas(),
            this.setupResizeObserver()
        },
        updatePattern(e) {
            this.getCurrentPatternUrl() !== e && (this.customPattern = e,
            this.rerenderCanvas())
        },
        resetPattern() {
            this.customPattern = "",
            this.rerenderCanvas()
        },
        setupResizeObserver() {
            let e = new ResizeObserver(()=>{
                this.rerenderCanvas(!0)
            }
            );
            e.observe(this.$target[0])
        },
        rerenderCanvas(e) {
            e ??= !1,
            this.patternRenderer.cleanup(this.$target[0]),
            e ? (this.patternRenderer.updateOptions(this.getRendererOptions()),
            this.patternRenderer.addCanvas(this.$target[0]),
            this.patternRenderer.renderToCanvas(this.$target[0])) : this.renderCanvas()
        },
        renderPromise: null,
        renderCanvas() {
            let e = ()=>{
                this.patternRenderer = a.getInstance(this.getRendererOptions()),
                this.patternRenderer.addCanvas(this.$target[0]),
                this.renderPromise = this.patternRenderer.renderToCanvas(this.$target[0])
            }
            ;
            this.renderPromise ? this.renderPromise.then(()=>{
                e()
            }
            ) : e()
        },
        getRendererOptions() {
            return {
                url: this.getCurrentPatternUrl(),
                width: this.$target.width(),
                height: this.$target.height(),
                mask: this.options.isDarkPattern
            }
        },
        getCurrentPatternUrl() {
            return this.customPattern || this.options.url
        }
    }),
    XF.Element.register("chat-canvas-pattern", "XF.ChatCanvasPattern");
    let s = {}
      , n = (e,t)=>{
        e instanceof HTMLImageElement || e instanceof HTMLVideoElement ? e.src = t : e instanceof SVGImageElement ? e.setAttributeNS(null, "href", t) : e.style.backgroundImage = "url(" + t + ")"
    }
      , i = "undefined" != typeof ImageBitmap;
    class a {
        static INSTANCES = [];
        constructor() {
            this.canvases = new Set
        }
        static getInstance(e) {
            let t = this.INSTANCES.find(t=>(function e(t, r) {
                let s = Object.keys
                  , n = typeof t;
                return t && r && "object" === n && n === typeof r ? s(t).length === s(r).length && s(t).every(s=>e(t[s], r[s])) : t === r
            }
            )(t.options, e));
            return t || ((t = new a).init(e),
            this.INSTANCES.push(t)),
            t
        }
        init(e) {
            this.options = e
        }
        updateOptions(e) {
            this.options = Object.assign(this.options, e)
        }
        renderToCanvas(e) {
            return this.renderImageFromUrl(this.options.url).then(()=>this.fillCanvas(e))
        }
        renderImageFromUrl(e) {
            var t, a, o;
            if (this.renderImageFromUrlPromise)
                return this.renderImageFromUrlPromise;
            let h = this.image = r.createElement("img");
            return h.crossOrigin = "anonymous",
            this.renderImageFromUrlPromise = (t = h,
            a = e,
            o = !1,
            new Promise(e=>{
                !function e(t, r, i, a=!0) {
                    if (!r) {
                        console.error("renderImageFromUrl: no url?", t, r),
                        i?.();
                        return
                    }
                    let o = t instanceof HTMLVideoElement;
                    if (s[r] && a || o)
                        t && n(t, r),
                        i?.();
                    else {
                        let h = t instanceof HTMLImageElement
                          , l = h ? t : new Image;
                        l.src = r;
                        let d = ()=>{
                            !h && t && n(t, r),
                            s[r] = !0,
                            i?.(),
                            l.removeEventListener("error", m)
                        }
                          , m = e=>{
                            console.error("Render image from url failed:", e, r, l),
                            l.removeEventListener("load", d),
                            i?.()
                        }
                        ;
                        l.addEventListener("load", d, {
                            once: !0
                        }),
                        l.addEventListener("error", m, {
                            once: !0
                        })
                    }
                }(t, a, e, o)
            }
            )).then(()=>i ? createImageBitmap(h, {
                resizeWidth: 1440,
                resizeHeight: 2960
            }).then(e=>(this.imageBitmap = e,
            h)) : h)
        }
        cleanup(e) {
            this.canvases.delete(e),
            !this.canvases.size && (function e(t, r) {
                let s = t.indexOf(r)
                  , n = -1 === s ? void 0 : t.splice(s, 1);
                return n?.[0]
            }(a.INSTANCES, this),
            this.objectUrl && (this.imageBitmap?.close(),
            URL.revokeObjectURL(this.objectUrl)))
        }
        fillCanvas(e) {
            let t = e.getContext("2d")
              , {width: r, height: s} = e
              , n = this.imageBitmap || this.image
              , i = n.width
              , a = n.height
              , o = (500 + e.offsetHeight / 2.5) * e.dpr
              , h = o / a;
            i *= h,
            a = o,
            this.options.mask ? (t.fillStyle = "#000",
            t.fillRect(0, 0, r, s),
            t.globalCompositeOperation = "destination-out") : t.globalCompositeOperation = "source-over";
            let l = e=>{
                for (let s = 0; s < r; s += i)
                    t.drawImage(n, s, e, i, a)
            }
              , d = (s - a) / 2;
            if (l(d),
            d > 0) {
                let m = d;
                do
                    l(m -= a);
                while (m >= 0)
            }
            let c = s - 1;
            for (let u = d + a; u < c; u += a)
                l(u)
        }
        setCanvasDimensions(e) {
            let r = Math.min(2, t.devicePixelRatio)
              , s = this.options.width * r
              , n = this.options.height * r;
            e.dpr = r,
            e.dataset.originalHeight = "" + n,
            e.width = s,
            e.height = n
        }
        createCanvas() {
            let e = r.createElement("canvas");
            return this.canvases.add(e),
            this.setCanvasDimensions(e),
            e
        }
        addCanvas(e) {
            this.canvases.add(e),
            this.setCanvasDimensions(e)
        }
        resize(e, t) {
            this.init({
                ...this.options,
                width: e,
                height: t
            });
            let r = [];
            for (let s of this.canvases)
                this.setCanvasDimensions(s),
                r.push(this.renderToCanvas(s));
            return Promise.all(r)
        }
        static resizeInstances(e, t) {
            return Promise.all(this.INSTANCES.map(r=>r.resize(e, t)))
        }
    }
}(window.jQuery, window, document);
