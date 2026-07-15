(function () {
    'use strict';

    var roots = document.querySelectorAll('[data-public-home-particles]');
    if (roots.length === 0 || typeof window.requestAnimationFrame !== 'function') {
        return;
    }

    var motionQuery = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    function colorChannels(value) {
        var match = String(value || '').match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i);
        if (!match) {
            return [0, 0, 0];
        }

        return [Number(match[1]), Number(match[2]), Number(match[3])];
    }

    function shuffle(items) {
        var index;
        for (index = items.length - 1; index > 0; index -= 1) {
            var targetIndex = Math.floor(Math.random() * (index + 1));
            var item = items[index];
            items[index] = items[targetIndex];
            items[targetIndex] = item;
        }

        return items;
    }

    function bindParticleEffect(root) {
        var canvas = root.querySelector('[data-public-home-particle-canvas]');
        var title = root.querySelector('.public-home-title');
        if (!canvas || !title || typeof canvas.getContext !== 'function') {
            return;
        }

        var context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        if (motionQuery && motionQuery.matches) {
            return;
        }

        var width = 0;
        var height = 0;
        var pixelRatio = 1;
        var particles = [];
        var animationFrame = 0;
        var animationStartedAt = 0;
        var particleFillStartedAt = 0;
        var assemblyCompletedAt = 0;
        var resizeTimer = 0;

        root.classList.add('is-public-home-particles-ready');

        function resizeCanvas() {
            var rect = canvas.getBoundingClientRect();
            pixelRatio = Math.min(window.devicePixelRatio || 1, 2);
            width = Math.max(1, rect.width);
            height = Math.max(1, rect.height);
            canvas.width = Math.round(width * pixelRatio);
            canvas.height = Math.round(height * pixelRatio);
            context.setTransform(pixelRatio, 0, 0, pixelRatio, 0, 0);
        }

        function sampledTitlePoints() {
            var sampleCanvas = document.createElement('canvas');
            var sampleContext = sampleCanvas.getContext('2d', {willReadFrequently: true});
            if (!sampleContext) {
                return [];
            }

            sampleCanvas.width = Math.ceil(width);
            sampleCanvas.height = Math.ceil(height);

            var canvasRect = canvas.getBoundingClientRect();
            var titleRect = title.getBoundingClientRect();
            var titleStyle = window.getComputedStyle(title);
            var fontSize = parseFloat(titleStyle.fontSize) || 80;
            var sampleStep = Math.max(3, Math.round(fontSize / 48));

            sampleContext.fillStyle = '#fff';
            sampleContext.font = titleStyle.fontWeight + ' ' + titleStyle.fontSize + ' ' + titleStyle.fontFamily;
            sampleContext.textAlign = 'left';
            sampleContext.textBaseline = 'alphabetic';
            if ('letterSpacing' in sampleContext) {
                sampleContext.letterSpacing = '0px';
            }

            var titleMetrics = sampleContext.measureText(title.textContent.trim());
            var lineHeight = parseFloat(titleStyle.lineHeight) || fontSize;
            var fontAscent = titleMetrics.fontBoundingBoxAscent || fontSize * .8;
            var fontDescent = titleMetrics.fontBoundingBoxDescent || fontSize * .2;
            var lineLeading = (lineHeight - fontAscent - fontDescent) / 2;

            var glyphs = [];
            var walker = document.createTreeWalker(title, window.NodeFilter.SHOW_TEXT);
            var textNode = walker.nextNode();
            while (textNode) {
                var offset = 0;
                while (offset < textNode.data.length) {
                    var codePoint = textNode.data.codePointAt(offset);
                    var character = String.fromCodePoint(codePoint);
                    var nextOffset = offset + character.length;
                    if (character.trim() !== '') {
                        var range = document.createRange();
                        range.setStart(textNode, offset);
                        range.setEnd(textNode, nextOffset);
                        var characterRect = range.getBoundingClientRect();
                        if (characterRect.width > 0 && characterRect.height > 0) {
                            glyphs.push({
                                character: character,
                                left: characterRect.left,
                                top: characterRect.top,
                            });
                        }
                    }
                    offset = nextOffset;
                }
                textNode = walker.nextNode();
            }

            var lineTops = [];
            glyphs.forEach(function (glyph) {
                var hasLine = lineTops.some(function (lineTop) {
                    return Math.abs(lineTop - glyph.top) < 2;
                });
                if (!hasLine) {
                    lineTops.push(glyph.top);
                }
            });
            lineTops.sort(function (first, second) {
                return first - second;
            });

            glyphs.forEach(function (glyph) {
                var lineIndex = 0;
                var nearestDistance = Infinity;
                lineTops.forEach(function (lineTop, index) {
                    var distance = Math.abs(lineTop - glyph.top);
                    if (distance < nearestDistance) {
                        nearestDistance = distance;
                        lineIndex = index;
                    }
                });

                var baselineY = titleRect.top - canvasRect.top
                    + lineIndex * lineHeight
                    + lineLeading
                    + fontAscent;
                sampleContext.fillText(glyph.character, glyph.left - canvasRect.left, baselineY);
            });

            var imageData = sampleContext.getImageData(0, 0, sampleCanvas.width, sampleCanvas.height).data;
            function pointsAtStep(step) {
                var sampledPoints = [];
                var x;
                var y;
                for (y = 0; y < sampleCanvas.height; y += step) {
                    for (x = 0; x < sampleCanvas.width; x += step) {
                        if (imageData[(y * sampleCanvas.width + x) * 4 + 3] > 96) {
                            sampledPoints.push({x: x, y: y});
                        }
                    }
                }

                return sampledPoints;
            }

            var maximumParticles = width < 700 ? 2600 : 5200;
            var points = pointsAtStep(sampleStep);
            if (points.length > maximumParticles) {
                sampleStep = Math.ceil(sampleStep * Math.sqrt(points.length / maximumParticles));
                points = pointsAtStep(sampleStep);
            }
            while (points.length > maximumParticles) {
                sampleStep += 1;
                points = pointsAtStep(sampleStep);
            }

            return shuffle(points);
        }

        function scatteredPosition(targetX, targetY) {
            var x = Math.random() * width;
            var y = Math.random() * height;
            var attempts = 0;

            while (Math.hypot(x - targetX, y - targetY) < Math.min(width, height) * .2 && attempts < 4) {
                x = Math.random() * width;
                y = Math.random() * height;
                attempts += 1;
            }

            if (Math.random() < .42) {
                var side = Math.floor(Math.random() * 4);
                if (side === 0) {
                    x = Math.random() * width;
                    y = Math.random() * height * .1;
                } else if (side === 1) {
                    x = width - Math.random() * width * .1;
                    y = Math.random() * height;
                } else if (side === 2) {
                    x = Math.random() * width;
                    y = height - Math.random() * height * .1;
                } else {
                    x = Math.random() * width * .1;
                    y = Math.random() * height;
                }
            }

            return {x: x, y: y};
        }

        function createParticles() {
            var targets = sampledTitlePoints();
            var foundationCount = Math.max(1, Math.round(targets.length * .58));
            var foundationArrival = 0;
            var fillArrival = 0;

            particles = targets.map(function (target, index) {
                var isFoundation = index < foundationCount;
                var start = isFoundation
                    ? scatteredPosition(target.x, target.y)
                    : {x: target.x, y: target.y};
                var delay = isFoundation ? Math.random() * 420 : 0;
                var duration = isFoundation
                    ? 1050 + Math.random() * 600
                    : 350 + Math.random() * 500;

                if (isFoundation) {
                    foundationArrival = Math.max(foundationArrival, delay + duration);
                }

                return {
                    startX: start.x,
                    startY: start.y,
                    targetX: target.x,
                    targetY: target.y,
                    delay: delay,
                    duration: duration,
                    isFoundation: isFoundation,
                    fillIndex: Math.max(0, index - foundationCount),
                    size: .55 + Math.random() * .75,
                    twinkle: Math.random() * Math.PI * 2,
                };
            });

            particleFillStartedAt = foundationArrival + 100;
            particles.forEach(function (particle) {
                if (particle.isFoundation) {
                    return;
                }

                var fillCount = Math.max(1, particles.length - foundationCount);
                var fillPosition = particle.fillIndex / fillCount;
                particle.delay = particleFillStartedAt + fillPosition * 650 + Math.random() * 90;
                fillArrival = Math.max(fillArrival, particle.delay + particle.duration);
            });
            assemblyCompletedAt = Math.max(foundationArrival, fillArrival) + 180;
        }

        function easeOutQuart(value) {
            return 1 - Math.pow(1 - value, 4);
        }

        function easeInOutCubic(value) {
            return value < .5
                ? 4 * value * value * value
                : 1 - Math.pow(-2 * value + 2, 3) / 2;
        }

        function drawParticle(particle, elapsed, channels) {
            if (!particle.isFoundation && elapsed < particle.delay) {
                return;
            }

            var localProgress = Math.max(0, Math.min(1, (elapsed - particle.delay) / particle.duration));
            var progress = easeOutQuart(localProgress);
            var x = particle.startX + (particle.targetX - particle.startX) * progress;
            var y = particle.startY + (particle.targetY - particle.startY) * progress;
            var drawSize = particle.size;
            if (!particle.isFoundation) {
                drawSize = particle.size * easeInOutCubic(localProgress);
            }
            var twinkle = .72 + Math.sin(elapsed / 240 + particle.twinkle) * .18;
            var alpha = localProgress === 0 ? twinkle * .42 : twinkle * (.52 + localProgress * .4);

            context.globalAlpha = Math.max(.16, Math.min(.92, alpha));
            context.fillStyle = 'rgb(' + channels[0] + ', ' + channels[1] + ', ' + channels[2] + ')';
            context.beginPath();
            context.arc(x, y, drawSize, 0, Math.PI * 2);
            context.fill();
        }

        function animate(timestamp) {
            if (animationStartedAt === 0) {
                animationStartedAt = timestamp;
            }

            var elapsed = timestamp - animationStartedAt;
            var channels = colorChannels(window.getComputedStyle(title).color);
            var index;

            context.clearRect(0, 0, width, height);
            for (index = 0; index < particles.length; index += 1) {
                drawParticle(particles[index], elapsed, channels);
            }
            context.globalAlpha = 1;

            if (elapsed < assemblyCompletedAt) {
                animationFrame = window.requestAnimationFrame(animate);
                return;
            }

            root.classList.add('is-public-home-particles-complete');
            animationFrame = 0;
            window.setTimeout(function () {
                context.clearRect(0, 0, width, height);
            }, 1500);
        }

        function startAnimation() {
            if (animationFrame !== 0) {
                window.cancelAnimationFrame(animationFrame);
            }
            root.classList.remove('is-public-home-particles-complete');
            animationStartedAt = 0;
            resizeCanvas();
            createParticles();
            animationFrame = window.requestAnimationFrame(animate);
        }

        function scheduleRestart() {
            if (root.classList.contains('is-public-home-particles-complete')) {
                return;
            }
            window.clearTimeout(resizeTimer);
            resizeTimer = window.setTimeout(startAnimation, 160);
        }

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(startAnimation, startAnimation);
        } else {
            startAnimation();
        }

        window.addEventListener('resize', scheduleRestart);
        if (motionQuery && typeof motionQuery.addEventListener === 'function') {
            motionQuery.addEventListener('change', function () {
                if (motionQuery.matches) {
                    if (animationFrame !== 0) {
                        window.cancelAnimationFrame(animationFrame);
                        animationFrame = 0;
                    }
                    context.clearRect(0, 0, width, height);
                    root.classList.remove('is-public-home-particles-ready');
                    root.classList.remove('is-public-home-particles-complete');
                } else {
                    root.classList.add('is-public-home-particles-ready');
                    startAnimation();
                }
            });
        }
    }

    Array.prototype.forEach.call(roots, bindParticleEffect);
}());
