!function(t, i, e) {
    "use strict";
    XF.ChatCanvasGradient = XF.Element.newHandler({
        options: {
            colors: []
        },
        gradientRenderer: null,
        init() {
            let {gradientRenderer: t} = o.create(this.options.colors, this.$target[0]);
            this.gradientRenderer = t,
            this.gradientRenderer.init(this.$target[0])
        },
        updateColors(t) {
            this.$target.attr("data-colors", t).data("colors", t),
            this.redraw()
        },
        redraw() {
            this.clear(),
            this.gradientRenderer.init(this.$target[0])
        },
        clear() {
            let t = this.$target[0].getContext("2d");
            t.clearRect(0, 0, this.$target[0].width, this.$target[0].height)
        }
    }),
    XF.Element.register("chat-canvas-gradient", "XF.ChatCanvasGradient");
    let s;
    function h(t) {
        s ? s.push(t) : (s = [t],
        requestAnimationFrame(()=>{
            let t = s;
            s = void 0,
            t.forEach(t=>t())
        }
        ))
    }
    let l = new Map;
    function a(t) {
        return l.get(t)
    }
    function n(t, i, e) {
        return e || (e = function t(i) {
            (function t(i) {
                let e = a(i);
                e && (e.isCancelled = !0,
                e.deferred.resolve())
            }
            )(i);
            let e = {
                isCancelled: !1,
                deferred: function t() {
                    let i = {
                        isFulfilled: !1,
                        isRejected: !1,
                        notify() {},
                        notifyAll(...t) {
                            i.lastNotify = t,
                            i.listeners.forEach(i=>i(...t))
                        },
                        listeners: [],
                        addNotifyListener(t) {
                            i.lastNotify && t(...i.lastNotify),
                            i.listeners.push(t)
                        }
                    }
                      , e = new Promise((t,s)=>{
                        i.resolve = i=>{
                            e.isFulfilled || e.isRejected || (e.isFulfilled = !0,
                            t(i))
                        }
                        ,
                        i.reject = (...t)=>{
                            e.isRejected || e.isFulfilled || (e.isRejected = !0,
                            s(...t))
                        }
                    }
                    );
                    return e.catch(noop).finally(()=>{
                        e.notify = e.notifyAll = e.lastNotify = null,
                        e.listeners.length = 0,
                        e.cancel && (e.cancel = noop)
                    }
                    ),
                    Object.assign(e, i),
                    e
                }()
            };
            return l.set(i, e),
            e.deferred.then(()=>{
                a(i) === e && l.delete(i)
            }
            ),
            e
        }(i)),
        h(()=>{
            !e.isCancelled && (t() ? n(t, i, e) : e.deferred.resolve())
        }
        ),
        e.deferred
    }
    function r(t) {
        h(()=>{
            t() && r(t)
        }
        )
    }
    class o {
        _width = 50;
        _height = 50;
        _tails = 90;
        _scrollTails = 50;
        _curve = [0, .25, .5, .75, 1, 1.5, 2, 2.5, 3, 3.5, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 18.3, 18.6, 18.9, 19.2, 19.5, 19.8, 20.1, 20.4, 20.7, 21, 21.3, 21.6, 21.9, 22.2, 22.5, 22.8, 23.1, 23.4, 23.7, 24, 24.3, 24.6, 24.9, 25.2, 25.5, 25.8, 26.1, 26.3, 26.4, 26.5, 26.6, 26.7, 26.8, 26.9, 27];
        _positions = [{
            x: .8,
            y: .1
        }, {
            x: .6,
            y: .2
        }, {
            x: .35,
            y: .25
        }, {
            x: .25,
            y: .6
        }, {
            x: .2,
            y: .9
        }, {
            x: .4,
            y: .8
        }, {
            x: .65,
            y: .75
        }, {
            x: .75,
            y: .4
        }];
        _phases = this._positions.length;
        constructor() {
            let t = this._tails / this._curve[this._curve.length - 1];
            for (let i = 0, e = this._curve.length; i < e; ++i)
                this._curve[i] = this._curve[i] * t;
            this._incrementalCurve = this._curve.map((t,i,e)=>t - (e[i - 1] ?? 0))
        }
        hexToRgb(t) {
            var i;
            let e = function t(i) {
                let e = []
                  , s = "#" === i[0] ? 1 : 0;
                if (i.length === 5 + s && (i = (s ? "#" : "") + "0" + i.slice(s)),
                i.length === 3 + s)
                    for (let h = s; h < i.length; ++h)
                        e.push(parseInt(i[h] + i[h], 16));
                else if (i.length === 4 + s) {
                    for (let l = s; l < i.length - 1; ++l)
                        e.push(parseInt(i[l] + i[l], 16));
                    e.push(parseInt(i[i.length - 1], 16))
                } else
                    for (let a = s; a < i.length; a += 2)
                        e.push(parseInt(i.slice(a, a + 2), 16));
                return e
            }((i = t).slice(0, 7));
            return {
                r: e[0],
                g: e[1],
                b: e[2]
            }
        }
        getPositions(t) {
            let i = this._positions.slice();
            i.push(...i.splice(0, t));
            let e = [];
            for (let s = 0; s < i.length; s += 2)
                e.push(i[s]);
            return e
        }
        getNextPositions(t, i, e) {
            let s = this.getPositions(t);
            if (!e[0] && 1 === e.length)
                return [s];
            let h = this.getPositions(++t % this._phases)
              , l = h.map((t,e)=>({
                x: (t.x - s[e].x) / i,
                y: (t.y - s[e].y) / i
            }))
              , a = e.map(t=>l.map((i,e)=>({
                x: s[e].x + i.x * t,
                y: s[e].y + i.y * t
            })));
            return a
        }
        curPosition(t, i) {
            let e = this.getNextPositions(t, this._tails, [i]);
            return e[0]
        }
        changeTail(t) {
            for (this._tail += t; this._tail >= this._tails; )
                this._tail -= this._tails,
                ++this._phase >= this._phases && (this._phase -= this._phases);
            for (; this._tail < 0; )
                this._tail += this._tails,
                --this._phase < 0 && (this._phase += this._phases)
        }
        onWheel = t=>{
            !this._animatingToNextPosition && (this._scrollDelta += t.deltaY,
            void 0 === this._onWheelRAF && (this._onWheelRAF = requestAnimationFrame(this.drawOnWheel)))
        }
        ;
        changeTailAndDraw(t) {
            this.changeTail(t);
            let i = this.curPosition(this._phase, this._tail);
            this.drawGradient(i)
        }
        drawOnWheel = ()=>{
            let t = this._scrollDelta / this._scrollTails;
            this._scrollDelta %= this._scrollTails;
            let i = t > 0 ? Math.floor(t) : Math.ceil(t);
            i && this.changeTailAndDraw(i),
            this._onWheelRAF = void 0
        }
        ;
        drawNextPositionAnimated = t=>{
            let i, e;
            if (t) {
                var s;
                let h = t();
                i = h >= 1;
                let l = -1 * (s = h) * (s - 2)
                  , a = this._nextPositionTail ?? 0
                  , n = this._nextPositionTail = this._nextPositionTails * l
                  , r = n - a;
                r && (this._nextPositionLeft -= r,
                this.changeTailAndDraw(r))
            } else {
                let o = this._frames;
                e = o.shift(),
                i = !o.length
            }
            return e && this.drawImageData(e),
            i && (this._nextPositionLeft = void 0,
            this._nextPositionTails = void 0,
            this._nextPositionTail = void 0,
            this._animatingToNextPosition = void 0),
            !i
        }
        ;
        getGradientImageData(t) {
            let i = this._hctx.createImageData(this._width, this._height)
              , e = i.data
              , s = 0;
            for (let h = 0; h < this._height; ++h) {
                let l = h / this._height
                  , a = l - .5
                  , n = a * a;
                for (let r = 0; r < this._width; ++r) {
                    let o = r / this._width
                      , $ = o - .5
                      , c = Math.sqrt($ * $ + n)
                      , d = .35 * c
                      , g = d * d * 6.4
                      , u = Math.sin(g)
                      , f = Math.cos(g)
                      , x = Math.max(0, Math.min(1, .5 + $ * f - a * u))
                      , _ = Math.max(0, Math.min(1, .5 + $ * u + a * f))
                      , p = 0
                      , m = 0
                      , v = 0
                      , w = 0;
                    for (let P = 0; P < this._colors.length; P++) {
                        let y = t[P].x
                          , T = t[P].y
                          , R = x - y
                          , A = _ - T
                          , C = Math.max(0, .9 - Math.sqrt(R * R + A * A));
                        p += C = C * C * C * C,
                        m += C * this._colors[P].r / 255,
                        v += C * this._colors[P].g / 255,
                        w += C * this._colors[P].b / 255
                    }
                    e[s++] = m / p * 255,
                    e[s++] = v / p * 255,
                    e[s++] = w / p * 255,
                    e[s++] = 255
                }
            }
            return i
        }
        drawImageData(t) {
            this._hctx.putImageData(t, 0, 0),
            this._ctx.drawImage(this._hc, 0, 0, this._width, this._height)
        }
        drawGradient(t) {
            this.drawImageData(this.getGradientImageData(t))
        }
        init(t) {
            this._frames = [],
            this._phase = 0,
            this._tail = 0,
            this._scrollDelta = 0,
            void 0 !== this._onWheelRAF && (cancelAnimationFrame(this._onWheelRAF),
            this._onWheelRAF = void 0);
            let i = t.getAttribute("data-colors").split(",").reverse();
            this._colors = i.map(t=>this.hexToRgb(t)),
            this._hc || (this._hc = e.createElement("canvas"),
            this._hc.width = this._width,
            this._hc.height = this._height,
            this._hctx = this._hc.getContext("2d", {
                alpha: !1
            })),
            this._canvas = t,
            this._ctx = this._canvas.getContext("2d", {
                alpha: !1
            }),
            this.update()
        }
        update() {
            if (this._colors.length < 2) {
                let t = this._colors[0];
                this._ctx.fillStyle = `rgb(${t.r}, ${t.g}, ${t.b})`,
                this._ctx.fillRect(0, 0, this._width, this._height);
                return
            }
            let i = this.curPosition(this._phase, this._tail);
            this.drawGradient(i)
        }
        toNextPosition(t) {
            if (this._colors.length < 2)
                return;
            if (t) {
                this._nextPositionLeft = this._tails + (this._nextPositionLeft ?? 0),
                this._nextPositionTails = this._nextPositionLeft,
                this._nextPositionTail = void 0,
                this._animatingToNextPosition = !0,
                n(this.drawNextPositionAnimated.bind(this, t), this);
                return
            }
            let i = this._tail, e = this._tails, s, h = [];
            for (let l = 0, a = this._incrementalCurve.length; l < a; ++l) {
                let r = this._incrementalCurve[l]
                  , o = (h[l - 1] ?? i) + r;
                +o.toFixed(2) > e && void 0 === s && (s = l,
                o %= e),
                h.push(o)
            }
            let $ = h.slice(0, s)
              , c = void 0 !== s ? h.slice(s) : [];
            [$, c].forEach((t,i,s)=>{
                let h = t[t.length - 1];
                if (void 0 !== h && h > e && (t[t.length - 1] = +h.toFixed(2)),
                this._tail = h ?? 0,
                !t.length)
                    return;
                let l = this.getNextPositions(this._phase, e, t);
                i !== s.length - 1 && ++this._phase >= this._phases && (this._phase -= this._phases);
                let a = l.map(t=>this.getGradientImageData(t));
                this._frames.push(...a)
            }
            ),
            this._animatingToNextPosition = !0,
            n(this.drawNextPositionAnimated, this)
        }
        scrollAnimate(t) {}
        cleanup() {
            this.scrollAnimate(!1)
        }
        static createCanvas(t, i) {
            return (i ??= e.createElement("canvas")).width = 50,
            i.height = 50,
            void 0 !== t && (i.dataset.colors = t),
            i
        }
        static create(t, i) {
            i = this.createCanvas(t, i);
            let e = new o;
            return e.init(i),
            {
                gradientRenderer: e,
                canvas: i
            }
        }
    }
}(window.jQuery, window, document);
