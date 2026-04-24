<style>
.progress-wrapper {
    position: relative;
    width: 60px;
    height: 60px;
}

.circleLoader {
    fill: none;
    stroke-width: 10;
}

.progress-wrapper .background {
    stroke: var(--border-dim);
}

.progress-wrapper .foreground {
    stroke: var(--status-success);
    stroke-linecap: round;
    transition: stroke-dashoffset 1s linear;
}

.progress-wrapper .percentage {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 18px;
    font-weight: 600;
    color: var(--status-success);
    letter-spacing: 1.2px;
}
</style>

<div class="progress-wrapper">
    <svg width="60" height="60" viewBox="0 0 150 150">
        <circle class="circleLoader background" cx="75" cy="75" r="70" />
        <circle class="circleLoader foreground" cx="75" cy="75" r="70" />
    </svg>
    <div class="percentage">0</div>
</div>

<script>
    const percentageElement = document.querySelector('.percentage');
    const foregroundCircle = document.querySelector('.foreground');
    const circumference = 2 * Math.PI * 70; // Circumference of the circle (2 * π * radius)
        
    // Set the initial stroke-dasharray and stroke-dashoffset
    foregroundCircle.style.strokeDasharray = circumference;
    foregroundCircle.style.strokeDashoffset = circumference;
    
    function updateLoader(totalTime, remainingTime){
        percentageElement.textContent = remainingTime;
        
        if (remainingTime <= 15) {
            percentageElement.style.color = "var(--status-warning)";
            foregroundCircle.style.stroke = "var(--status-warning)";
        }else{
            percentageElement.style.color = "var(--status-success)";
            foregroundCircle.style.stroke = "var(--status-success)";
        }
        
        if (remainingTime <= 0) {
            foregroundCircle.style.strokeDashoffset = 0;
        } else {
            const percentage = Math.round(((totalTime-remainingTime) / totalTime) * 100);
            const offset = circumference - (percentage / 100) * circumference;
            foregroundCircle.style.strokeDashoffset = offset;
        }
    }
</script>