// jQuery가 없는 환경에서 오류 방지
if (typeof jQuery === 'undefined') {
    var $ = function(selector) {
        var noop = function() { return this; };
        return {
            ready: noop, click: noop, on: noop, off: noop,
            mouseover: noop, mouseout: noop, hover: noop,
            focusin: noop, focusout: noop,
            addClass: noop, removeClass: noop, toggleClass: noop,
            attr: noop, val: noop, find: noop, closest: noop, siblings: noop,
            prepend: noop, append: noop
        };
    };
    $.fn = {};
}

// 전역 변수
var errmsg = "";
var errfld = null;

// 필드 검사
function check_field(fld, msg) {
    if ((fld.value = trim(fld.value)) == "")
        error_field(fld, msg);
    else
        clear_field(fld);
    return;
}

// 필드 오류 표시
function error_field(fld, msg) {
    if (msg != "")
        errmsg += msg + "\n";
    if (!errfld) errfld = fld;
    fld.style.background = "#BDDEF7";
}

// 필드를 깨끗하게
function clear_field(fld) {
    fld.style.background = "#FFFFFF";
}

function trim(s) {
    var t = "";
    var from_pos = to_pos = 0;

    for (i = 0; i < s.length; i++) {
        if (s.charAt(i) == ' ')
            continue;
        else {
            from_pos = i;
            break;
        }
    }

    for (i = s.length; i >= 0; i--) {
        if (s.charAt(i - 1) == ' ')
            continue;
        else {
            to_pos = i;
            break;
        }
    }

    t = s.substring(from_pos, to_pos);
    //				alert(from_pos + ',' + to_pos + ',' + t+'.');
    return t;
}

// 자바스크립트로 PHP의 number_format 흉내를 냄
// 숫자에 , 를 출력
function number_format(data) {

    var tmp = '';
    var number = '';
    var cutlen = 3;
    var comma = ',';
    var i;

    data = data + '';

    var sign = data.match(/^[\+\-]/);
    if (sign) {
        data = data.replace(/^[\+\-]/, "");
    }

    len = data.length;
    mod = (len % cutlen);
    k = cutlen - mod;
    for (i = 0; i < data.length; i++) {
        number = number + data.charAt(i);

        if (i < data.length - 1) {
            k++;
            if ((k % cutlen) == 0) {
                number = number + comma;
                k = 0;
            }
        }
    }

    if (sign != null)
        number = sign + number;

    return number;
}

// , 를 없앤다.
function no_comma(data) {
    var tmp = '';
    var comma = ',';
    var i;

    for (i = 0; i < data.length; i++) {
        if (data.charAt(i) != comma)
            tmp += data.charAt(i);
    }
    return tmp;
}

// 삭제 검사 확인
function del(href) {
    if (confirm("한번 삭제한 자료는 복구할 방법이 없습니다.\n\n정말 삭제하시겠습니까?")) {
        window.location.href = href;
    }
}

// 쿠키 입력
function set_cookie(name, value, expirehours, domain) {
    var today = new Date();
    today.setTime(today.getTime() + (60 * 60 * 1000 * expirehours));
    document.cookie = name + "=" + escape(value) + "; path=/; expires=" + today.toGMTString() + ";";
    if (domain) {
        document.cookie += "domain=" + domain + ";";
    }
}

// 쿠키 얻음
function get_cookie(name) {
    var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    if (match) return unescape(match[2]);
    return "";
}

// 쿠키 지움
function delete_cookie(name) {
    var today = new Date();

    today.setTime(today.getTime() - 1);
    var value = get_cookie(name);
    if (value != "")
        document.cookie = name + "=" + value + "; path=/; expires=" + today.toGMTString();
}

var last_id = null;
function menu(id) {
    if (id != last_id) {
        if (last_id != null)
            document.getElementById(last_id).style.display = "none";
        document.getElementById(id).style.display = "block";
        last_id = id;
    }
    else {
        document.getElementById(id).style.display = "none";
        last_id = null;
    }
}

function textarea_decrease(id, row) {
    if (document.getElementById(id).rows - row > 0)
        document.getElementById(id).rows -= row;
}

function textarea_original(id, row) {
    document.getElementById(id).rows = row;
}

function textarea_increase(id, row) {
    document.getElementById(id).rows += row;
}

// 글숫자 검사
function check_byte(content, target) {
    var i = 0;
    var cnt = 0;
    var ch = '';
    var cont = document.getElementById(content).value;

    for (i = 0; i < cont.length; i++) {
        ch = cont.charAt(i);
        if (escape(ch).length > 4) {
            cnt += 2;
        } else {
            cnt += 1;
        }
    }
    // 숫자를 출력
    document.getElementById(target).innerHTML = cnt;

    return cnt;
}

// 브라우저에서 오브젝트의 왼쪽 좌표
function get_left_pos(obj) {
    var parentObj = null;
    var clientObj = obj;
    //var left = obj.offsetLeft + document.body.clientLeft;
    var left = obj.offsetLeft;

    while ((parentObj = clientObj.offsetParent) != null) {
        left = left + parentObj.offsetLeft;
        clientObj = parentObj;
    }

    return left;
}

// 브라우저에서 오브젝트의 상단 좌표
function get_top_pos(obj) {
    var parentObj = null;
    var clientObj = obj;
    //var top = obj.offsetTop + document.body.clientTop;
    var top = obj.offsetTop;

    while ((parentObj = clientObj.offsetParent) != null) {
        top = top + parentObj.offsetTop;
        clientObj = parentObj;
    }

    return top;
}

function flash_movie(src, ids, width, height, wmode) {
    var wh = "";
    if (parseInt(width) && parseInt(height))
        wh = " width='" + width + "' height='" + height + "' ";
    return "<object classid='clsid:d27cdb6e-ae6d-11cf-96b8-444553540000' codebase='http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0' " + wh + " id=" + ids + "><param name=wmode value=" + wmode + "><param name=movie value=" + src + "><param name=quality value=high><embed src=" + src + " quality=high wmode=" + wmode + " type='application/x-shockwave-flash' pluginspage='http://www.macromedia.com/shockwave/download/index.cgi?p1_prod_version=shockwaveflash' " + wh + "></embed></object>";
}

function obj_movie(src, ids, width, height, autostart) {
    var wh = "";
    if (parseInt(width) && parseInt(height))
        wh = " width='" + width + "' height='" + height + "' ";
    if (!autostart) autostart = false;
    return "<embed src='" + src + "' " + wh + " autostart='" + autostart + "'></embed>";
}

function doc_write(cont) {
    document.write(cont);
}

var win_password_lost = function (href) {
    window.open(href, "win_password_lost", "left=50, top=50, width=617, height=330, scrollbars=1");
}

document.addEventListener('DOMContentLoaded', function () {
    var passwordLostLinks = document.querySelectorAll('#login_password_lost, #ol_password_lost');
    passwordLostLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            win_password_lost(this.href);
        });
    });
});

/**
 * 쪽지 창
 **/
var check_goto_new = function (href, event) {
    if (!(typeof g5_is_mobile != "undefined" && g5_is_mobile)) {
        if (window.opener && window.opener.document && window.opener.document.getElementById) {
            event.preventDefault ? event.preventDefault() : (event.returnValue = false);
            window.open(href);
            //window.opener.document.location.href = href;
        }
    }
}

/**
 * 새로운 비밀번호 분실 창 : 101123
 **/
win_password_lost = function (href) {
    var new_win = window.open(href, 'win_password_lost', 'width=617, height=330, scrollbars=1');
    new_win.focus();
}

/**
 * 스크린리더 미사용자를 위한 스크립트 - 지운아빠 2013-04-22
 * alt 값만 갖는 그래픽 링크에 마우스오버 시 title 값 부여, 마우스아웃 시 title 값 제거
 **/
$(function () {
    $('a img').mouseover(function () {
        $a_img_title = $(this).attr('alt');
        $(this).attr('title', $a_img_title);
    }).mouseout(function () {
        $(this).attr('title', '');
    });
});

/**
 * 텍스트 리사이즈
**/
/**
 * 댓글 수정 토큰
**/
function set_comment_token(f) {
    return true;
}

$(function () {
    $(".win_password_lost").click(function () {
        win_password_lost(this.href);
        return false;
    });

    // 사이드뷰
    var sv_hide = false;
    $(".sv_member, .sv_guest").click(function () {
        $(".sv").removeClass("sv_on");
        $(this).closest(".sv_wrap").find(".sv").addClass("sv_on");
    });

    $(".sv, .sv_wrap").hover(
        function () {
            sv_hide = false;
        },
        function () {
            sv_hide = true;
        }
    );

    $(".sv_member, .sv_guest").focusin(function () {
        sv_hide = false;
        $(".sv").removeClass("sv_on");
        $(this).closest(".sv_wrap").find(".sv").addClass("sv_on");
    });

    $(".sv a").focusin(function () {
        sv_hide = false;
    });

    $(".sv a").focusout(function () {
        sv_hide = true;
    });

    // 셀렉트 ul
    var sel_hide = false;
    $('.sel_btn').click(function () {
        $('.sel_ul').removeClass('sel_on');
        $(this).siblings('.sel_ul').addClass('sel_on');
    });

    $(".sel_wrap").hover(
        function () {
            sel_hide = false;
        },
        function () {
            sel_hide = true;
        }
    );

    $('.sel_a').focusin(function () {
        sel_hide = false;
    });

    $('.sel_a').focusout(function () {
        sel_hide = true;
    });

    $(document).click(function () {
        if (sv_hide) { // 사이드뷰 해제
            $(".sv").removeClass("sv_on");
        }
        if (sel_hide) { // 셀렉트 ul 해제
            $('.sel_ul').removeClass('sel_on');
        }
    });

    $(document).focusin(function () {
        if (sv_hide) { // 사이드뷰 해제
            $(".sv").removeClass("sv_on");
        }
        if (sel_hide) { // 셀렉트 ul 해제
            $('.sel_ul').removeClass('sel_on');
        }
    });

    $(document).on("keyup change", "textarea#wr_content[maxlength]", function () {
        var str = $(this).val();
        var mx = parseInt($(this).attr("maxlength"));
        if (str.length > mx) {
            $(this).val(str.substr(0, mx));
            return false;
        }
    });
});

