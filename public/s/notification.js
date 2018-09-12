let notify = function(msg, params = {}){
    let wrapper = document.querySelector('.notifyWrapper')
    if (!wrapper) {
        wrapper = document.createElement('div')
        wrapper.style = `position:fixed; max-width:300px; width:100%; top:0; right:0; overflow:no-scroll;`
        wrapper.className = 'notifyWrapper'
        document.querySelector('body').appendChild(wrapper)
    }

    let ele = document.createElement('div')
    wrapper.appendChild(ele)

    ele.textContent = msg
    ele.style = `
        position:absolute;
        padding:0.5em 1em;
        margin:5px;
        max-width:250px;
        width:100%;
        border-radius:3px;
        color:white;
        transition:all 0.3s;
        box-sizing:border-box;
    `;
    ele.style.backgroundColor = params.bgcolor || '#666';
    ele.style.right = "-" + ele.offsetWidth + "px"

    let top = 0
    let oldOnes = document.querySelectorAll('.notify') || []
    oldOnes.forEach(function(e){
        top = e.offsetTop + e.offsetHeight
    })
    ele.style.top = top + "px";
    ele.className = 'notify'

    setTimeout(function(){
        ele.style.right = '5px';
    }, 34)

    let time = params.time || 2000
    if (time) {
        setTimeout(function(){
            ele.style.right = "-" + (ele.offsetWidth + 20) + "px"
        }, time + 300)

        setTimeout(function(){
            ele.parentElement.removeChild(ele)
        }, time + 600)
    }
}
