// The showcase carousel's Swiper, loaded only on pages that render the block.
// Swiper was a static import once, which put ~40KB of carousel into every page —
// memorial pages included, which have no carousel at all.
import Swiper from 'swiper';
import { Autoplay, FreeMode } from 'swiper/modules';
import 'swiper/css';

export function initShowcaseSwiper(el) {
    return new Swiper(el, {
        modules: [Autoplay, FreeMode],
        slidesPerView: 1.2,
        spaceBetween: 16,
        loop: true,
        speed: 5000,
        freeMode: { enabled: true, momentum: false },
        autoplay: {
            delay: 0,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
        },
        breakpoints: {
            640: { slidesPerView: 2.2, spaceBetween: 20 },
            1024: { slidesPerView: 3.2, spaceBetween: 24 },
            1280: { slidesPerView: 4, spaceBetween: 24 },
        },
    });
}
