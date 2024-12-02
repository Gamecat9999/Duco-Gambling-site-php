<!DOCTYPE html>
<html>
<head>
    <title>KatFaucet Games</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .header {
            text-align: center;
            padding: 40px 20px;
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }

        h1 {
            color: #ffcc00;
            font-size: 3em;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .subtitle {
            color: #fff;
            opacity: 0.8;
            margin-top: 10px;
        }

        .games-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            padding: 40px 20px;
            max-width: 1200px;
        }

        .game-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            width: 300px;
            text-align: center;
            transition: all 0.3s ease;
            backdrop-filter: blur(5px);
            border: 2px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .game-card:hover {
            transform: translateY(-10px);
            border-color: #ffcc00;
            box-shadow: 0 10px 30px rgba(255, 204, 0, 0.2);
        }

        .game-icon {
            font-size: 3em;
            margin-bottom: 20px;
            color: #ffcc00;
        }

        .game-title {
            font-size: 1.8em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #ffcc00;
        }

        .game-description {
            font-size: 1em;
            color: #fff;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .odds {
            background: rgba(255, 204, 0, 0.15);
            padding: 10px;
            border-radius: 10px;
            font-weight: bold;
            color: #ffcc00;
        }

        .footer {
            text-align: center;
            padding: 30px;
            background: rgba(0, 0, 0, 0.3);
            width: 100%;
            margin-top: auto;
        }

        @media (max-width: 768px) {
            .game-card {
                width: 100%;
                max-width: 300px;
            }

            h1 {
                font-size: 2.2em;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>KatGames</h1>
        <div class="subtitle">Play, Win, and Earn DUCO</div>
    </div>

    <div class="games-container">
        <a href="coinflip.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="game-title">Coin Flip</div>
            <div class="game-description">
                Choose heads or tails and double your DUCO! Simple, fast, and exciting.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win 1 DUCO</div>
        </a>

        <a href="mines.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-bomb"></i>
            </div>
            <div class="game-title">Mines</div>
            <div class="game-description">
                Avoid the mines and collect gems to multiply your winnings! Cash out anytime.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 20 DUCO</div>
        </a>

        <a href="diceroll.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-dice"></i>
            </div>
            <div class="game-title">Dice Roll</div>
            <div class="game-description">
                Predict if the roll will be over or under your number! Higher risk, higher reward.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 99x</div>
        </a>

        <a href="1in10.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-dice"></i>
            </div>
            <div class="game-title">Lucky 10</div>
            <div class="game-description">
                Pick a number from 1-10 and multiply your DUCO by 5x!
            </div>
            <div class="odds">Bet 2.5 DUCO → Win 5 DUCO</div>
        </a>

        <a href="1in100.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-gem"></i>
            </div>
            <div class="game-title">Century Shot</div>
            <div class="game-description">
                Feeling lucky? Pick 1-100 for a chance at a massive payout!
            </div>
            <div class="odds">Bet 5 DUCO → Win 10 DUCO</div>
        </a>

        <a href="crash.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="game-title">Crash</div>
            <div class="game-description">
                Watch the multiplier rise and cash out before it crashes! The longer you wait, the more you can win.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 10x</div>
        </a>

        <a href="plinko.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-circle"></i>
            </div>
            <div class="game-title">Plinko</div>
            <div class="game-description">
                Drop the ball and watch it bounce! Choose your risk level for different rewards.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 5x</div>
        </a>

        <a href="tower.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-chess-rook"></i>
            </div>
            <div class="game-title">Tower</div>
            <div class="game-description">
                Climb the tower and choose safe spots! The higher you climb, the bigger the rewards.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 25x</div>
        </a>

        <a href="roulette.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-sync"></i>
            </div>
            <div class="game-title">Roulette</div>
            <div class="game-description">
                Place your bets and watch the wheel spin! Choose numbers, colors, or combinations.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 35x</div>
        </a>

        <a href="blackjack.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-crown"></i>
            </div>
            <div class="game-title">Blackjack</div>
            <div class="game-description">
                Classic casino card game! Get closer to 21 than the dealer without going over.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 2.5x</div>
        </a>

        <a href="cardwar.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-chess-queen"></i>
            </div>
            <div class="game-title">Card War</div>
            <div class="game-description">
                Draw your card and beat the dealer! Higher card wins with special multipliers.
            </div>
            <div class="odds">Bet 0.5 DUCO → Win up to 2.5x</div>
        </a>
        


        <a href="colormatch.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-palette"></i>
            </div>
            <div class="game-title">Color Match</div>
            <div class="game-description">
                Match the colors to win! Get three of the same color for the biggest prize.
            </div>
            <div class="odds">Bet 0.25 DUCO → Win 0.5 DUCO</div>
        </a>

        <a href="luckyletters.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-font"></i>
            </div>
            <div class="game-title">Lucky Letters</div>
            <div class="game-description">
                Match letters or spell special words to win! Triple matches and bonus words give bigger prizes.
            </div>
            <div class="odds">Bet 0.25 DUCO → Win up to 0.5 DUCO</div>
        </a>

        <a href="cardpairs.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-clone"></i>
            </div>
            <div class="game-title">Card Pairs</div>
            <div class="game-description">
                Find matching pairs of cards! Higher value cards give better rewards.
            </div>
            <div class="odds">Bet 0.25 DUCO → Win up to 0.5 DUCO</div>
        </a>

        <a href="gemcascade.php" class="game-card">
            <div class="game-icon">
                <i class="fas fa-gem"></i>
            </div>
            <div class="game-title">Gem Cascade</div>
            <div class="game-description">
                Match 3 or more gems in a row or column to win! Longer matches give better rewards.
            </div>
            <div class="odds">Bet 0.25 DUCO → Win up to 0.5 DUCO</div>
        </a>
    </div>

    <div style="text-align: center; padding: 20px; margin: 20px auto; max-width: 800px; background: rgba(255, 255, 255, 0.05); border-radius: 15px;">
        <h3 style="color: #ffcc00; margin-bottom: 15px;">Security Notice</h3>
        <p>Your security is our priority. We never store passwords in any files.</p>
        <p>All account security is handled through secure privacy systems and is never stored.</p>
    </div>

    <div class="footer">
        <p>All games are provably fair and open source.</p>
        <p style="color: #aaa; font-size: 0.9em;">© 2024 KatFaucet</p>
    </div>
</body>
</html>